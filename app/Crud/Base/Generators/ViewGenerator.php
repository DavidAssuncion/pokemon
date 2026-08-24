<?php

declare(strict_types=1);

namespace App\Crud\Base\Generators;

use Illuminate\Support\Str;

class ViewGenerator
{
    public function make(string $module, array $schema): array
    {
        return [
            'index' => $this->index($module, $schema),
            'form' => $this->form($module, $schema),
            'show' => $this->show($module, $schema),
        ];
    }

    private function isForeignKey(string $columnName, array $schema): ?string
    {
        foreach ($schema['relations'] as $rel) {
            if ($rel->column_name === $columnName) {
                return Str::camel(Str::replaceLast('_id', '', $columnName));
            }
        }

        return null;
    }

    private function foreignTable(string $columnName, array $schema): ?string
    {
        foreach ($schema['relations'] as $rel) {
            if ($rel->column_name === $columnName) {
                return strtolower($rel->foreign_table);
            }
        }

        return null;
    }

    private function columnValue(string $columnName, array $schema): string
    {
        $rel = $this->isForeignKey($columnName, $schema);
        if ($rel) {
            return "{{ \$item->{$rel}->name ?? \$item->{$rel}->description ?? \$item->{$columnName} }}";
        }

        return "{{ \$item->{$columnName} }}";
    }

    private function index(string $module, array $schema): string
    {
        $route = Str::kebab($module);

        $headers = '';
        $columns = '';

        foreach ($schema['columns'] as $col) {
            if (in_array($col->column_name, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $label = Str::title(str_replace('_', ' ', $col->column_name));
            $headers .= "            <th>{$label}</th>\n";
            $columns .= "                    <td>{$this->columnValue($col->column_name, $schema)}</td>\n";
        }

        return <<<BLADE
<h1>{$module} list</h1>

<a href="{{ route('{$route}.create') }}">Create</a>
<a href="{{ route('cruds') }}">Back to dashboard</a>

<table>
    <thead>
        <tr>
            <th>ID</th>
{$headers}            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @forelse(\$items as \$item)
            <tr>
                <td>{{ \$item->id }}</td>
{$columns}                <td>
                    <a href="{{ route('{$route}.show', \$item) }}">View</a>
                    <a href="{{ route('{$route}.edit', \$item) }}">Edit</a>
                    <form method="POST" action="{{ route('{$route}.destroy', \$item) }}" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button onclick="return confirm('Are you sure?')">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="100%">No records found.</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ \$items->links() }}
BLADE;
    }

    private function form(string $module, array $schema): string
    {
        $route = Str::kebab($module);
        $fields = '';

        foreach ($schema['columns'] as $col) {
            if (in_array($col->column_name, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $name = $col->column_name;
            $label = Str::title(str_replace('_', ' ', $name));

            $relTable = $this->foreignTable($name, $schema);
            if ($relTable) {
                $rel = $this->isForeignKey($name, $schema);
                $fields .= <<<BLADE

<label>{$label}</label>
<select name="{$name}">
    <option value="">Select {$label}</option>
    @foreach(\${$relTable} as \$optValue => \$optLabel)
        <option value="{{ \$optValue }}" {{ old('{$name}', \$item->{$name} ?? '') == \$optValue ? 'selected' : '' }}>{{ \$optLabel }}</option>
    @endforeach
</select>
BLADE;
            } else {
                $inputType = in_array($col->data_type, ['integer', 'bigint'], true) ? 'number' : 'text';
                $fields .= <<<BLADE

<label>{$label}</label>
<input type="{$inputType}" name="{$name}" value="{{ old('{$name}', \$item->{$name} ?? '') }}">
BLADE;
            }
        }

        return <<<BLADE
<h1>{{ isset(\$item) ? 'Edit {$module}' : 'Create {$module}' }}</h1>

<a href="{{ route('{$route}.index') }}">Back</a>

<form method="POST" action="{{ isset(\$item) ? route('{$route}.update', \$item) : route('{$route}.store') }}">
    @csrf
    @isset(\$item)
        @method('PUT')
    @endisset

{$fields}

    <button>Save</button>
</form>
BLADE;
    }

    private function show(string $module, array $schema): string
    {
        $route = Str::kebab($module);
        $fields = '';

        foreach ($schema['columns'] as $col) {
            $label = Str::title(str_replace('_', ' ', $col->column_name));
            $fields .= <<<BLADE
    <tr>
        <th>{$label}</th>
        <td>{$this->columnValue($col->column_name, $schema)}</td>
    </tr>

BLADE;
        }

        return <<<BLADE
<h1>{$module} detail</h1>

<table>
    <tbody>
{$fields}    </tbody>
</table>

<a href="{{ route('{$route}.index') }}">Back</a>
<a href="{{ route('{$route}.edit', \$item) }}">Edit</a>
<form method="POST" action="{{ route('{$route}.destroy', \$item) }}" style="display:inline">
    @csrf
    @method('DELETE')
    <button onclick="return confirm('Are you sure?')">Delete</button>
</form>
BLADE;
    }
}
