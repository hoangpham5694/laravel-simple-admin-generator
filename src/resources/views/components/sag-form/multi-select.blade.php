@props(['name', 'label' => null, 'id' => null, 'options' => [], 'selected' => [], 'help' => null])

@php($inputName = str_ends_with($name, '[]') ? $name : $name.'[]')
@php($oldKey = preg_replace('/\[\]$/', '', $name))
@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $oldKey), '_'))
@php($hasError = ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($oldKey))
@php($selectedValues = old($oldKey, $selected))
@php($selectedValues = is_iterable($selectedValues) ? collect($selectedValues)->map(static fn ($value) => (string) $value)->all() : [(string) $selectedValues])

<x-sag-form.field :name="$oldKey" :label="$label" :id="$fieldId" :help="$help">
    <select name="{{ $inputName }}" id="{{ $fieldId }}" multiple {{ $attributes->except('multiple')->class(['form-control', 'is-invalid' => $hasError]) }}>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $selectedValues, true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-sag-form.field>
