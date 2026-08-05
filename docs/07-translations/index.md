# Translations

Translations live in `resources/lang/{lang}/messages.php` and are published with the `filament-recommendations-translations` tag.

## Customize an existing language

Publish the translations and edit them:

```bash
php artisan vendor:publish --tag=filament-recommendations-translations
```

```php
// resources/lang/vendor/filament-recommendations/en/messages.php
return [
    'widgets' => [
        'recommendations' => [
            'empty' => 'Nothing to recommend yet.',
        ],
    ],
];
```

## Add a new language

Create `resources/lang/vendor/filament-recommendations/{lang}/messages.php` and return the translated keys. The keys mirror the English file under `resources/lang/en/messages.php`.
