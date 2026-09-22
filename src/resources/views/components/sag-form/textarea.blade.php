@props(['name', 'label' => null, 'id' => null, 'value' => null, 'rows' => 3, 'help' => null])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($hasError = ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($name))

<x-sag-form.field :name="$name" :label="$label" :id="$fieldId" :help="$help">
    <textarea name="{{ $name }}" id="{{ $fieldId }}" rows="{{ $rows }}" {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>{{ old($name, $value) }}</textarea>
</x-sag-form.field>
