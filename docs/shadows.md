# Read-only copies (shadows)

When a service needs another service's rows locally, for example to join, filter or sort on them,
it can keep a copy in its own database, kept up to date by events. Only the source service writes
the data.

```php
// billing: the source; the $shadowed fields are copied
final class Customer extends Model implements \Microservices\Contracts\Shadows\Shadowed
{
    use \Microservices\Traits\ShadowSource;

    protected array $shadowed = ['name'];
}

// foundation: the copy as billing describes it, for every service that keeps one
class CustomerShadow extends \Microservices\Models\ShadowModel
{
    public static function owner(): string { return 'billing'; }

    public static function sourceTable(): string { return 'customers'; }
}

// orders, config/microservices.php: the copies it keeps, in its own database
'shadows' => [\Foundation\Billing\Shadows\CustomerShadow::class],
```

A keeper lists the foundation's class as it is, or a subclass of its own that adds relations,
casts or a `beforeSync()`. Two forms of one source need two tables: the second sets `$table` and
brings its own migration. A copy is a model like any other: it reads the database of the service
running, so it is read inside that service.

The keeper creates the copy's table with a migration extending
`Microservices\Migrations\ShadowMigration`, whose `table()` is the name the keeper's copy model reads: the source's own, or its `$table`. The copy has
the source's key as its own, the shadowed columns and a `deleted_at`:

```php
return new class extends \Microservices\Migrations\ShadowMigration
{
    protected function source(): string { return 'customers'; }

    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->softDeletes();
        });
    }
};
```

```
billing: Customer saved / deleted ─► microservices.shadow.changed ─► orders consumer ─► CustomerShadow::sync()
```

A copy rejects any write that doesn't come from `sync()`, and a deleted source row becomes a soft
delete in the copy. Override `beforeSync()` to derive what the copy needs and the source never
announced, and `shouldBeShadowed()` to keep only some rows: a row it returns `false` for is never
written, and leaves the copy if it was there.

```php
// analytics keeps only the administrators
protected static function shouldBeShadowed(array $attributes): bool
{
    return (bool) $attributes['is_admin'];
}
```

To fill a copy created after the source already had data:

```bash
php artisan shadows:want [--keepers=orders] [--sources=customers]   # on the keeper: ask the owners to send their rows again
php artisan shadows:announce customers [--keepers=orders]          # on the owner: send every row again
```

Both commands address the event through its `recipients`, so only those services update their
copy. A service in another language can be the source: see
[Services in other languages](other-languages.md#being-the-source-of-a-copy).
