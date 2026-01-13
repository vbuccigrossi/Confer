<?php

namespace Tests\Unit;

use App\Services\SearchQueryParser;
use Tests\TestCase;

class Unit__SearchQueryParser__ParsingTest extends TestCase
{
    public function test_parses_simple_terms(): void
    {
        $parser = new SearchQueryParser('hello world');
        
        $this->assertEquals(['hello', 'world'], $parser->getTerms());
        $this->assertEmpty($parser->getPhrases());
        $this->assertEmpty($parser->getNegations());
    }

    public function test_parses_quoted_phrases(): void
    {
        $parser = new SearchQueryParser('"hello world" test');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertEquals(['hello world'], $parser->getPhrases());
    }

    public function test_parses_multiple_phrases(): void
    {
        $parser = new SearchQueryParser('"first phrase" "second phrase"');
        
        $this->assertEmpty($parser->getTerms());
        $this->assertEquals(['first phrase', 'second phrase'], $parser->getPhrases());
    }

    public function test_parses_negations(): void
    {
        $parser = new SearchQueryParser('hello -world -foo');
        
        $this->assertEquals(['hello'], $parser->getTerms());
        $this->assertEquals(['world', 'foo'], $parser->getNegations());
    }

    public function test_parses_in_filter(): void
    {
        $parser = new SearchQueryParser('test in:#general');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertEquals('#general', $parser->getFilter('in'));
    }

    public function test_parses_from_filter(): void
    {
        $parser = new SearchQueryParser('test from:@alice');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertEquals('@alice', $parser->getFilter('from'));
    }

    public function test_parses_has_file_filter(): void
    {
        $parser = new SearchQueryParser('test has:file');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertEquals('file', $parser->getFilter('has'));
    }

    public function test_parses_date_filters(): void
    {
        $parser = new SearchQueryParser('test since:2024-01-01 until:2024-12-31');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertEquals('2024-01-01', $parser->getFilter('since'));
        $this->assertEquals('2024-12-31', $parser->getFilter('until'));
    }

    public function test_parses_global_filter(): void
    {
        $parser = new SearchQueryParser('test global');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertTrue($parser->hasFilter('global'));
    }

    public function test_parses_global_with_colon(): void
    {
        $parser = new SearchQueryParser('test global:true');
        
        $this->assertEquals(['test'], $parser->getTerms());
        $this->assertEquals('true', $parser->getFilter('global'));
    }

    public function test_parses_complex_query(): void
    {
        $parser = new SearchQueryParser('hello "exact phrase" -exclude in:#general from:@alice');
        
        $this->assertEquals(['hello'], $parser->getTerms());
        $this->assertEquals(['exact phrase'], $parser->getPhrases());
        $this->assertEquals(['exclude'], $parser->getNegations());
        $this->assertEquals('#general', $parser->getFilter('in'));
        $this->assertEquals('@alice', $parser->getFilter('from'));
    }

    public function test_builds_tsquery_from_terms(): void
    {
        $parser = new SearchQueryParser('hello world');
        
        $tsquery = $parser->toTsQuery();
        
        $this->assertEquals('hello:* & world:*', $tsquery);
    }

    public function test_builds_tsquery_from_phrase(): void
    {
        $parser = new SearchQueryParser('"hello world"');
        
        $tsquery = $parser->toTsQuery();
        
        $this->assertEquals('(hello:* <-> world:*)', $tsquery);
    }

    public function test_builds_tsquery_with_negation(): void
    {
        $parser = new SearchQueryParser('hello -world');
        
        $tsquery = $parser->toTsQuery();
        
        $this->assertEquals('hello:* & !world:*', $tsquery);
    }

    public function test_builds_complex_tsquery(): void
    {
        $parser = new SearchQueryParser('hello "exact phrase" -exclude');
        
        $tsquery = $parser->toTsQuery();
        
        $this->assertEquals('hello:* & (exact:* <-> phrase:*) & !exclude:*', $tsquery);
    }

    public function test_is_empty_with_no_content(): void
    {
        $parser = new SearchQueryParser('');
        
        $this->assertTrue($parser->isEmpty());
    }

    public function test_is_empty_with_only_filters(): void
    {
        $parser = new SearchQueryParser('in:#general from:@alice');
        
        $this->assertTrue($parser->isEmpty());
    }

    public function test_is_not_empty_with_terms(): void
    {
        $parser = new SearchQueryParser('hello');
        
        $this->assertFalse($parser->isEmpty());
    }

    public function test_handles_whitespace(): void
    {
        $parser = new SearchQueryParser('  hello   world  ');
        
        $this->assertEquals(['hello', 'world'], $parser->getTerms());
    }

    public function test_handles_special_characters_in_terms(): void
    {
        $parser = new SearchQueryParser('hello@world test#123');
        
        // Special characters should be stripped in tsquery
        $tsquery = $parser->toTsQuery();
        $this->assertStringContainsString('helloworld:*', $tsquery);
        $this->assertStringContainsString('test123:*', $tsquery);
    }
}
