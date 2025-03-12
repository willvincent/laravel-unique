# Unique Names

[![Latest Version on Packagist](https://img.shields.io/packagist/v/willvincent/laravel-unique.svg?style=flat-square)](https://packagist.org/packages/willvincent/laravel-unique)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/willvincent/laravel-unique/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/willvincent/laravel-unique/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/willvincent/laravel-unique/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/willvincent/laravel-unique/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/willvincent/laravel-unique.svg?style=flat-square)](https://packagist.org/packages/willvincent/laravel-unique)

A trait for Laravel Eloquent models to ensure a field remains unique within specified constraints. 

It offers flexible suffix formats or custom value generators, making it ideal for scenarios like unique names, slugs, or identifiers.

## How It Works

The `HasUniqueNames` trait hooks into the Laravel Eloquent `saving` event, which fires before a model is persisted
to the database (on both create and update operations). It checks if the designated unique field (e.g., `name`)
already exists within the defined constraints (e.g., `organization_id`). If a duplicate is detected:

- On **create**, it generates a unique value.
- On **update**, it only adjusts the field if it has changed (i.e., if it’s “dirty”).

If a duplicate exists, the trait either appends a suffix (e.g., `Foo (1)`) or uses a custom generator to produce a unique value.

## Features

- Enforces uniqueness at the application level before saving.
- Supports custom suffix formats (e.g., ` ({n})` or `-{n}`).
- Allows custom value generators for advanced uniqueness logic.
- Configurable via a config file or model properties.
- Handles constraints (e.g., uniqueness within a specific scope).

## Installation

Install the package via Composer:

```bash
composer require willvincent/laravel-unique
```

Publish the configuration file (optional) to customize defaults:

```bash
php artisan vendor:publish --tag="laravel-unique-config"
```

Here’s the default configuration file (`config/unique_names.php`):

```php
return [
    /*
    |-----------------------------------------------------------------------------------------
    | Unique Name Field
    |-----------------------------------------------------------------------------------------
    | The default field name to enforce uniqueness on.
    */
    'unique_field' => 'name',

    /*
    |-----------------------------------------------------------------------------------------
    | Constraint Fields
    |-----------------------------------------------------------------------------------------
    | Fields defining the scope of uniqueness. For example, to ensure unique equipment names
    | within a department, set 'constraint_fields' to ['department_id'].
    */
    'constraint_fields' => [],

    /*
    |-----------------------------------------------------------------------------------------
    | Suffix Format
    |-----------------------------------------------------------------------------------------
    | Defines how suffixes are appended to duplicates. Use '{n}' as a placeholder for the number.
    | Examples: ' ({n})' → 'Foo (1)', '-{n}' → 'foo-1'.
    */
    'suffix_format' => ' ({n})',

    /*
    |-----------------------------------------------------------------------------------------
    | Deduplication Max Tries
    |-----------------------------------------------------------------------------------------
    | Maximum attempts to generate a unique value before throwing an exception.
    */
    'max_tries' => 10,
];
```

## Usage

Add the `HasUniqueNames` trait to your Eloquent model and optionally configure it:

```php
use WillVincent\LaravelUnique\HasUniqueNames;

class YourModel extends Model
{
    use HasUniqueNames;

    // Optional: Override default settings
    protected $uniqueField = 'name';              // Field to keep unique (default: 'name')
    protected $constraintFields = ['organization_id']; // Scope of uniqueness (default: [])
    protected $uniqueSuffixFormat = ' ({n})';     // Suffix format (default: ' ({n})')
}
```

### Configuration Options

You can customize the trait’s behavior either in the `config/unique_names.php` file or by overriding properties in your model:

- **`uniqueField`**: The field to enforce uniqueness on (default: `'name'`).
- **`constraintFields`**: Array of fields defining the uniqueness scope (default: `[]`).
- **`uniqueSuffixFormat`**: Format for suffixes, with `{n}` as the number placeholder (default: `' ({n})'`).
- **`uniqueValueGenerator`**: Optional custom generator (see below).

Model properties take precedence over config file settings.

### Examples

#### Default Suffix

Ensure names are unique within an organization:

```php
protected $uniqueField = 'name';
protected $constraintFields = ['organization_id'];
protected $uniqueSuffixFormat = ' ({n})';
```

- Input: `name: "Foo", organization_id: 1`
    - Output: `"Foo"` (if unique)
    - Output: `"Foo (1)"` (if `"Foo"` exists)
    - Output: `"Foo (2)"` (if `"Foo"` and `"Foo (1)"` exist)

#### Slug Format

Use a slug-friendly suffix:

```php
protected $uniqueField = 'slug';
protected $constraintFields = ['organization_id'];
protected $uniqueSuffixFormat = '-{n}';
```

- Input: `slug: "bar", organization_id: 1`
    - Output: `"bar"` (if unique)
    - Output: `"bar-1"` (if `"bar"` exists)

#### Custom Generator

Define a custom method or callable for unique values:

**Method on Model:**

```php
protected $uniqueValueGenerator = 'generateUniqueSlug';

public function generateUniqueSlug(string $base, array $constraints, ?int $attempt): string
{
    return $base . '-' . \Str::random(5);
}
```

**Callable:**

```php
protected $uniqueValueGenerator = function (string $base, array $constraints, int $attempt): string {
    return $base . '-' . \Str::random(5);
};
```

- Input: `name: "baz"`
    - Output: `"baz-abc12"` (random 5-character suffix)

The generator receives the base value, constraint values, and the retry attempt, and must return a unique string.
It retries up to `max_tries` times if the generated value isn’t unique, the first attempt will be 0, retries will
be numbered 1 through your limit.

## Advanced Configuration

### Custom Generator Details

The custom generator can be:

- A **string**: The name of a method on the model.
- A **callable**: An anonymous function or closure.

If the generated value isn’t unique, the trait retries with increasing attempt counts until `max_tries` is reached, then throws an exception.

### Database Considerations

The trait enforces uniqueness at the application level. For data integrity, especially in high-concurrency scenarios, consider adding database-level unique constraints (e.g., unique indexes) alongside this trait.

## Testing

The package includes a test suite covering 93% of the code, testing:

- Uniqueness on create and update
- Suffix formats
- Custom generators
- Constraint handling
- Edge cases (e.g., null constraints, max tries)

Run the tests with:

```bash
composer test
```

## Source Code

View or contribute to the package on GitHub: [willvincent/laravel-unique](https://github.com/willvincent/laravel-unique)

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent updates.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Review [our security policy](../../security/policy) to report issues.

## Credits

- [Will Vincent](https://github.com/willvincent)
- [All Contributors](../../contributors)

## License

MIT License. See [LICENSE](LICENSE.md) for more information.
