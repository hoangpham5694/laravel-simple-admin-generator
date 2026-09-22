@props(['name', 'label' => null, 'id' => null, 'value' => null, 'type' => 'text', 'help' => null])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($hasError = ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($name))
@php($oldValue = old($name, $value))

<x-sag-form.field :name="$name" :label="$label" :id="$fieldId" :help="$help">
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        @if ($type !== 'file' && ! ($type === 'password' && old($name) === null)) value="{{ $oldValue }}" @endif
        {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
    >
</x-sag-form.field>
