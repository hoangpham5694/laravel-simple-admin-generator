@props(['name', 'label' => null, 'id' => null, 'help' => null, 'value' => null])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($hasError = ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($name))

<x-sag-form.field :name="$name" :label="$label" :id="$fieldId" :help="$help">
    <input type="file" name="{{ $name }}" id="{{ $fieldId }}" {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>
</x-sag-form.field>
