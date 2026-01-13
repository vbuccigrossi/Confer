<?php

namespace App\Services;

/**
 * Parse search query strings with support for:
 * - Plain terms (hello world)
 * - Quoted phrases ("exact phrase")
 * - Negation (-term)
 * - Filters (in:#channel, from:@user, has:file, since:2024-01-01, until:2024-12-31, global)
 */
class SearchQueryParser
{
    protected string $rawQuery;
    protected array $terms = [];
    protected array $phrases = [];
    protected array $negations = [];
    protected array $filters = [];

    public function __construct(string $query)
    {
        $this->rawQuery = trim($query);
        $this->parse();
    }

    /**
     * Parse the query string
     */
    protected function parse(): void
    {
        $query = $this->rawQuery;
        $offset = 0;
        $length = strlen($query);

        while ($offset < $length) {
            // Skip whitespace
            if (ctype_space($query[$offset])) {
                $offset++;
                continue;
            }

            // Check for filters (in:, from:, has:, since:, until:, before:, after:, on:, global)
            if (preg_match('/^(in|from|has|since|until|before|after|on|global):([^\s]+)/i', substr($query, $offset), $matches)) {
                $this->filters[$matches[1]] = $matches[2];
                $offset += strlen($matches[0]);
                continue;
            }

            // Check for special case: 'global' without colon
            if (preg_match('/^global\b/i', substr($query, $offset), $matches)) {
                $this->filters['global'] = true;
                $offset += strlen($matches[0]);
                continue;
            }

            // Check for quoted phrase
            if ($query[$offset] === '"') {
                $endQuote = strpos($query, '"', $offset + 1);
                if ($endQuote !== false) {
                    $phrase = substr($query, $offset + 1, $endQuote - $offset - 1);
                    if (!empty($phrase)) {
                        $this->phrases[] = $phrase;
                    }
                    $offset = $endQuote + 1;
                    continue;
                }
            }

            // Check for negation
            if ($query[$offset] === '-' && $offset + 1 < $length && !ctype_space($query[$offset + 1])) {
                // Extract the term to negate
                $match = [];
                if (preg_match('/^-([^\s]+)/', substr($query, $offset), $match)) {
                    $this->negations[] = $match[1];
                    $offset += strlen($match[0]);
                    continue;
                }
            }

            // Extract regular term
            if (preg_match('/^([^\s]+)/', substr($query, $offset), $match)) {
                $term = $match[1];
                if (!empty($term) && !str_starts_with($term, 'in:') && !str_starts_with($term, 'from:')) {
                    $this->terms[] = $term;
                }
                $offset += strlen($match[0]);
            }
        }
    }

    /**
     * Get all plain search terms
     */
    public function getTerms(): array
    {
        return $this->terms;
    }

    /**
     * Get all quoted phrases
     */
    public function getPhrases(): array
    {
        return $this->phrases;
    }

    /**
     * Get all negated terms
     */
    public function getNegations(): array
    {
        return $this->negations;
    }

    /**
     * Get all filters
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get filter value by key
     */
    public function getFilter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
    }

    /**
     * Check if a filter exists
     */
    public function hasFilter(string $key): bool
    {
        return isset($this->filters[$key]);
    }

    /**
     * Build PostgreSQL tsquery from terms and phrases
     */
    public function toTsQuery(): string
    {
        $parts = [];

        // Add terms with AND operator
        foreach ($this->terms as $term) {
            $parts[] = $this->escapeTsQueryTerm($term);
        }

        // Add phrases (treated as exact matches)
        foreach ($this->phrases as $phrase) {
            // For phrases, we need to join words with <->
            $words = array_filter(explode(' ', $phrase));
            if (count($words) === 1) {
                $parts[] = $this->escapeTsQueryTerm($words[0]);
            } else {
                $phraseTerms = array_map(fn($w) => $this->escapeTsQueryTerm($w), $words);
                $parts[] = '(' . implode(' <-> ', $phraseTerms) . ')';
            }
        }

        // Add negations with NOT operator
        foreach ($this->negations as $negation) {
            $parts[] = '!' . $this->escapeTsQueryTerm($negation);
        }

        if (empty($parts)) {
            return '';
        }

        return implode(' & ', $parts);
    }

    /**
     * Escape a term for use in tsquery
     */
    protected function escapeTsQueryTerm(string $term): string
    {
        // Remove special characters that could break tsquery
        $term = preg_replace('/[^a-zA-Z0-9_]/', '', $term);
        
        // Add :* suffix for prefix matching
        return $term . ':*';
    }

    /**
     * Check if query is empty (no terms, phrases, or meaningful content)
     */
    public function isEmpty(): bool
    {
        return empty($this->terms) && empty($this->phrases) && empty($this->negations);
    }

    /**
     * Get the raw query string
     */
    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}
