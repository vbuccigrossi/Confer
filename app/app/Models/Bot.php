<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bot extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'avatar_url',
        'webhook_url',
        'config_schema',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config_schema' => 'array',
    ];

    /**
     * Supported configuration field types.
     */
    public const CONFIG_FIELD_TYPES = [
        'string',   // Single-line text input
        'text',     // Multi-line textarea
        'number',   // Numeric input
        'boolean',  // Toggle/checkbox
        'select',   // Dropdown with options
        'url',      // URL input with validation
        'secret',   // Password-style input (stored encrypted)
    ];

    /**
     * Check if this bot requires configuration.
     */
    public function requiresConfiguration(): bool
    {
        $schema = $this->config_schema;
        if (empty($schema) || empty($schema['fields'])) {
            return false;
        }

        // Check if any fields are required
        foreach ($schema['fields'] as $field) {
            if (!empty($field['required'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the configuration schema fields.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConfigFields(): array
    {
        return $this->config_schema['fields'] ?? [];
    }

    /**
     * Validate configuration values against the schema.
     *
     * @param array<string, mixed> $config Configuration values to validate
     * @return array<string, string> Validation errors (field => error message)
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        $fields = $this->getConfigFields();

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'string';
            $required = $field['required'] ?? false;
            $value = $config[$name] ?? null;

            // Check required fields
            if ($required && ($value === null || $value === '')) {
                $errors[$name] = 'This field is required.';
                continue;
            }

            // Skip validation if empty and not required
            if ($value === null || $value === '') {
                continue;
            }

            // Type-specific validation
            switch ($type) {
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$name] = 'Must be a number.';
                    } else {
                        $numValue = (float) $value;
                        if (isset($field['min']) && $numValue < $field['min']) {
                            $errors[$name] = "Must be at least {$field['min']}.";
                        }
                        if (isset($field['max']) && $numValue > $field['max']) {
                            $errors[$name] = "Must be at most {$field['max']}.";
                        }
                    }
                    break;

                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[$name] = 'Must be a valid URL.';
                    }
                    break;

                case 'boolean':
                    if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                        $errors[$name] = 'Must be true or false.';
                    }
                    break;

                case 'select':
                    $options = array_column($field['options'] ?? [], 'value');
                    if (!in_array($value, $options, true)) {
                        $errors[$name] = 'Invalid selection.';
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * Get default configuration values from schema.
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        $defaults = [];
        foreach ($this->getConfigFields() as $field) {
            if (isset($field['default'])) {
                $defaults[$field['name']] = $field['default'];
            }
        }
        return $defaults;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function installations(): HasMany
    {
        return $this->hasMany(BotInstallation::class);
    }

    public function isInstalledInWorkspace(int $workspaceId): bool
    {
        return $this->installations()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->exists();
    }
}
