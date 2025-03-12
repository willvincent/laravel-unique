<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use WillVincent\LaravelUnique\Tests\models\Item;

uses(RefreshDatabase::class);

it('makes name unique on create', function () {
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $item = Item::create(['name' => 'Foo', 'organization_id' => 1]);
    expect($item->name)->toBe('Foo (1)');
});

it('does not change name if unique on create', function () {
    $item = Item::create(['name' => 'Bar', 'organization_id' => 1]);
    expect($item->name)->toBe('Bar');
});

it('does not change name if not dirty on update', function () {
    $item = Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $item->organization_id = 2;
    $item->save();
    expect($item->name)->toBe('Foo');
});

it('makes name unique if changed on update', function () {
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $item = Item::create(['name' => 'Bar', 'organization_id' => 1]);
    $item->name = 'Foo';
    $item->save();
    expect($item->name)->toBe('Foo (1)');
});

it('makes name unique if creating another duplicate', function () {
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $item = Item::create(['name' => 'Bar', 'organization_id' => 1]);
    $item->name = 'Foo';
    $item->save();
    expect($item->name)->toBe('Foo (2)');
});

it('uses custom suffix format', function () {
    $model = new class extends Item {
        protected string $uniqueSuffixFormat = '-{n}';
    };
    $model->fill(['name' => 'Foo', 'organization_id' => 1])->save();
    $model->newInstance(['name' => 'Foo', 'organization_id' => 1])->save();

    expect($model->find(2)->name)->toBe('Foo-1');
});

it('uses custom value generator', function () {
    $model = new class extends Item {
        protected string $uniqueValueGenerator = 'generateUniqueName';
        public function generateUniqueName($base, $constraints): string
        {
            return $base . '-' . \Illuminate\Support\Str::random(5);
        }
    };
    $model::create(['name' => 'Foo', 'organization_id' => 1]);
    $item = $model::create(['name' => 'Foo', 'organization_id' => 1]);
    expect($item->name)->toMatch('/Foo-[a-zA-Z0-9]{5}/');
});

it('handles no constraints', function () {
    $model = new class extends Item {
        protected $constraintFields = [];
    };
    $model->fill(['name' => 'Foo'])->save();
    $model->newInstance(['name' => 'Foo'])->save();
    expect($model->find(2)->name)->toBe('Foo (1)');
});

it('defaults to name if uniqueField not set', function () {
    $model = new class extends Model {
        use \WillVincent\LaravelUnique\HasUniqueNames;
        protected $table = 'items';
        protected $fillable = ['name', 'organization_id'];
    };
    $model->fill(['name' => 'Foo', 'organization_id' => 1])->save();
    $model->newInstance(['name' => 'Foo', 'organization_id' => 1])->save();
    expect($model->find(2)->name)->toBe('Foo (1)');
});

it('enforces uniqueness with multiple constraints', function () {
    Config::set('unique_names.constraint_fields', ['organization_id', 'department_id']);
    Item::create([
        'name' => 'Foo',
        'organization_id' => 1,
        'department_id' => 1
    ]);
    $item2 = Item::create([
        'name' => 'Foo',
        'organization_id' => 1,
        'department_id' => 2
    ]);
    expect($item2->name)->toBe('Foo'); // Different dept, same org: allowed

    $item3 = Item::create([
        'name' => 'Foo',
        'organization_id' => 1,
        'department_id' => 1
    ]);
    expect($item3->name)->toBe('Foo (1)'); // Same org and dept: unique suffix

    $item4 = Item::create([
        'name' => 'Foo',
        'organization_id' => 2,
        'department_id' => 3
    ]);
    expect($item4->name)->toBe('Foo'); // Different org: allowed
});

it('enforces global uniqueness with no constraints', function () {
    $model = new class extends Item { protected $constraintFields = []; };
    $model->fill(['name' => 'Foo'])->save();
    $second = $model->newInstance(['name' => 'Foo']);
    $second->save();
    expect($second->name)->toBe('Foo (1)');
});

it('handles null constraint values', function () {
    Item::create(['name' => 'Foo', 'organization_id' => null]);
    $item = Item::create(['name' => 'Foo', 'organization_id' => null]);
    expect($item->name)->toBe('Foo (1)');
});

it('handles case sensitivity correctly', function () {
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $item = Item::create(['name' => 'foo', 'organization_id' => 1]);
    expect($item->name)->toBe('foo'); // Assuming case-sensitive DB
});

it('ensures unique names in sequential operations', function () {
    for ($i = 0; $i < 5; $i++) {
        Item::create(['name' => 'Foo', 'organization_id' => 1]);
    }
    $names = Item::pluck('name')->unique()->count();
    expect($names)->toBe(5); // Foo, Foo (1), Foo (2), Foo (3), Foo (4)
});

it('handles overlapping operations with delays', function () {
    $promises = [];
    for ($i = 0; $i < 5; $i++) {
        $promises[] = function () {
            usleep(rand(1000, 50000)); // Random delay between 1ms and 50ms
            Item::create(['name' => 'Foo', 'organization_id' => 1]);
        };
    }
    foreach ($promises as $promise) {
        $promise();
    }
    $names = Item::pluck('name')->unique()->count();
    expect($names)->toBe(5);
});

it('ensures unique names under sequential load', function () {
    for ($i = 0; $i < 5; $i++) {
        \Illuminate\Support\Facades\DB::transaction(function () {
            Item::create(['name' => 'Foo', 'organization_id' => 1]);
        });
    }
    $names = Item::pluck('name')->unique()->count();
    expect($names)->toBe(5);
});

it('uses custom generator with multiple constraints', function () {
    $model = new class extends Item {
        protected $constraintFields = ['organization_id', 'department_id'];
        protected $uniqueValueGenerator = 'generateUniqueName';
        public function generateUniqueName($base, $constraints) {
            return $base . '-' . $constraints['organization_id'] . '-' . $constraints['department_id'];
        }
    };

    $model::create(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $second = $model->newInstance(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $second->save();
    expect($second->name)->toBe("Foo-1-1");
});

it('uses a callable generator', function () {
    $model = new class extends Item {
        protected $constraintFields = ['organization_id', 'department_id'];
        protected $uniqueValueGenerator;

        public function __construct()
        {
            parent::__construct();
            $this->uniqueValueGenerator = function (string $base, array $constraints, int $attempts) {
                return $base . '-' . $constraints['organization_id'] . '-' . $constraints['department_id'];
            };
        }
    };

    $model::create(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $second = $model->newInstance(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $second->save();
    expect($second->name)->toBe("Foo-1-1");
});

it('throws an exception if custom generator isn\'t a string or callable', function () {
    $model = new class extends Item {
        protected $table = 'items'; // Ensure table is explicitly set if needed
        protected $constraintFields = ['organization_id', 'department_id'];
        protected $uniqueValueGenerator = []; // Invalid type
    };

    $model::create(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('uniqueValueGenerator must be a method name or a callable');

    $model::create(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
});

it('throws and exception if the suffix format is missing {n}', function () {
    $model = new class extends Item {
        protected $table = 'items'; // Ensure table is explicitly set if needed
        protected $uniqueSuffixFormat = 'meh';
    };

    $model::create(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('uniqueSuffixFormat must contain {n}');

    $model::create(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
});

it('limits maximum attempts', function () {
    $model = new class extends Item {
        protected $table = 'items'; // Explicitly set the table
        protected $constraintFields = ['organization_id'];
        protected $uniqueValueGenerator = 'generateUniqueName';
        public function generateUniqueName($base, $constraints) {
            return $base . '-' . $constraints['organization_id'];
        }
    };

    $first = $model->fill(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $first->save();
    expect($first->exists)->toBeTrue();
    expect($first->name)->toBe('Foo');

    $second = $model->newInstance(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $second->save();
    expect($second->exists)->toBeTrue();
    expect($second->name)->toBe('Foo-1');

    $third = $model->newInstance(['name' => 'Foo', 'organization_id' => 1, 'department_id' => 1]);
    $this->expectException(Exception::class);
    $third->save();
});

it('handles special characters', function () {
    Item::create(['name' => 'Café', 'organization_id' => 1]);
    $item = Item::create(['name' => 'Café', 'organization_id' => 1]);
    expect($item->name)->toBe('Café (1)');
});

it('performs well with large datasets', function () {
    $iterations = 250000;
    $items = [];
    for ($x = 0; $x < $iterations; $x++) {
        $name = 'Foo';
        if ($x > 0) {
            $name = 'Foo (' . $x .')';
        }
        $items[] = $name;
    }

    $chunks = collect($items)->chunk(1000);
    $chunks->map(function ($chunk) {
        DB::statement('INSERT INTO items (name, organization_id) VALUES '. implode(',', array_fill(0, $chunk->count(), '(?,?)')),
          $chunk->flatMap(fn ($item) => [ $item, 1 ])->toArray());
    });

    $start = microtime(true);
    $item = Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $duration = microtime(true) - $start;
    expect($duration)->toBeLessThan(3); // Adjust threshold as needed
    expect($item->name)->toBe("Foo ($iterations)");
});

it('retries with callable generator when initial value exists', function () {
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    Item::create(['name' => 'Foo-conflict', 'organization_id' => 1]);

    $model = new class extends Item {
        protected $table = 'items';
        protected $constraintFields = ['organization_id'];
        protected $uniqueValueGenerator;

        public function __construct()
        {
            parent::__construct();
            $this->uniqueValueGenerator = function (string $base, array $constraints, int $attempt) {
                return $attempt === 0 ? $base . '-conflict' : $base . '-unique-' . $attempt;
            };
        }
    };

    $item = $model::create(['name' => 'Foo', 'organization_id' => 1]);

    expect($item->name)->toBe('Foo-unique-1');
});

it('collects suffix numbers from multiple existing records', function () {
    // Pre-populate with records having suffixes
    Item::create(['name' => 'Foo', 'organization_id' => 1]);
    Item::create(['name' => 'Foo (1)', 'organization_id' => 1]);
    Item::create(['name' => 'Foo (3)', 'organization_id' => 1]); // Intentional gap

    // Create a new item with the same base name
    $item = Item::create(['name' => 'Foo', 'organization_id' => 1]);

    // Assert that it picks the next available number after collecting existing ones
    expect($item->name)->toBe('Foo (4)');
});

it('triggers while loop in default logic on update with multiple collisions', function () {
    // Step 1: Set up existing records with a gap and an extra collision
    $item1 = Item::create(['name' => 'Foo (2)', 'organization_id' => 1]); // id=1
    Item::create(['name' => 'Foo', 'organization_id' => 1]);              // id=2
    Item::create(['name' => 'Foo (1)', 'organization_id' => 1]);          // id=3
    Item::create(['name' => 'Foo (3)', 'organization_id' => 1]);          // id=4
    Item::create(['name' => 'Foo (4)', 'organization_id' => 1]);          // id=5
    Item::create(['name' => 'Foo (5)', 'organization_id' => 1]);          // id=6

    // Step 2: Update item1 (id=1, currently 'Foo (2)') to 'Foo'
    $item1->name = 'Foo';
    $item1->save();

    // Step 3: Verify the outcome
    expect($item1->name)->toBe('Foo (6)');
});

it('ignores soft-deleted records when uniqueIncludesTrashed is false', function () {
    // Set config to default (exclude trashed records)
    config(['unique_names.soft_delete' => false]);
    expect(method_exists(Item::class, 'bootSoftDeletes'))->toBeTrue();

    // Create and soft-delete an item
    $deletedItem = Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $deletedItem->delete();

    // Create a new item with the same name
    $newItem = Item::create(['name' => 'Foo', 'organization_id' => 1]);

    // Assert the new item keeps the original name
    expect($newItem->name)->toBe('Foo');
});

it('includes soft-deleted records when uniqueIncludesTrashed is true', function () {
    // Set config to include trashed records
    config(['unique_names.soft_delete' => true]);
    expect(method_exists(Item::class, 'bootSoftDeletes'))->toBeTrue();
    // Create and soft-delete an item
    $deletedItem = Item::create(['name' => 'Foo', 'organization_id' => 1]);
    $deletedItem->delete();

    // Create a new item with the same name
    $newItem = Item::create(['name' => 'Foo', 'organization_id' => 1]);

    // Assert the new item has a unique name
    expect($newItem->name)->toBe('Foo (1)');
});

it('ignores soft-delete logic when the model doesn\'t support soft deletes', function () {
    // Set config to include trashed records
    config(['unique_names.soft_delete' => true]);

    $model = new class extends Model {

        use \WillVincent\LaravelUnique\HasUniqueNames;

        protected $table = 'items';
        protected $constraintFields = ['organization_id'];

        protected $fillable = ['name', 'organization_id'];
    };
    expect(method_exists($model, 'bootSoftDeletes'))->toBeFalse();

    // Create and soft-delete an item
    $deletedItem = $model::create(['name' => 'Foo', 'organization_id' => 1]);
    $deletedItem->delete();

    // Create a new item with the same name
    $newItem = $model::create(['name' => 'Foo', 'organization_id' => 1]);


    expect($newItem->name)->toBe('Foo');
});
