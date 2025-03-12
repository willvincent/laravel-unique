<?php

return [
    /*
    |-----------------------------------------------------------------------------------------
    | Unique Name Field
    |-----------------------------------------------------------------------------------------
    |
    | This is the default field name that you want to enforce uniqueness upon.
    |
    */
    'unique_field' => 'name',

    /*
    |-----------------------------------------------------------------------------------------
    | Constraint Fields
    |-----------------------------------------------------------------------------------------
    |
    | Name uniqueness will be enforced within the context of any fields specified here.
    | Suppose you have a department_id on your equipment model, and want to ensure that
    | each piece of equipment in a given department is uniquely named, but the same
    | name within another departments is fine. You would then specify the
    | department_id as a constraint field.
    |
    */
    'constraint_fields' => [],

    /*
    |-----------------------------------------------------------------------------------------
    | Suffix Format
    |-----------------------------------------------------------------------------------------
    |
    | Define how you would like to append uniqueness suffixes to your duplicate names.
    | The default, ' ({n})' will append a space and the duplication count in parentheses.
    |
    | Foo will become Foo (1) and the next duplicate Foo (2) and so on.
    |
    | For slugs, you might instead set the format to '-{n}' so that a slug: my-duplicate-slug
    | becomes my-duplicate-slug-2 and so on.
    |
    */
    'suffix_format' => ' ({n})',

    /*
    |-----------------------------------------------------------------------------------------
    | Deduplication Max Tries
    |-----------------------------------------------------------------------------------------
    |
    | The maximum number of attempts to make to find a unique value. This setting prevents
    | the trait from getting stuck in an endless loop, though since it is grabbing the
    | largest value from the database that ideally should never happen anyway.
    |
    */
    'max_tries' => 10,

    /*
    |-----------------------------------------------------------------------------------------
    | Include Trashed
    |-----------------------------------------------------------------------------------------
    |
    | When set to true, soft-deleted items will also be considered when determining
    | uniqueness. This can be helpful if there's a chance those soft-deleted items
    | might be restored at some point in the future and you don't want names to
    | clash after restoration.
    |
    */
    'with_trashed' => false,
];
