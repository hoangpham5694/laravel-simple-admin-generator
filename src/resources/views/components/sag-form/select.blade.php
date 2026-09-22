@props(['name', 'label' => null, 'id' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'help' => null])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($hasError = ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($name))
@php($selectedValue = old($name, $selected))

<x-sag-form.field :name="$name" :label="$label" :id="$fieldId" :help="$help">
    <select name="{{ $name }}" id="{{ $fieldId }}" {{ $attributes->except('multiple')->class(['form-control', 'is-invalid' => $hasError]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selectedValue === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-sag-form.field>
