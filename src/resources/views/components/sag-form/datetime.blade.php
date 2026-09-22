@props(['name', 'label' => null, 'id' => null, 'value' => null, 'help' => null, 'minuteIncrement' => 5, 'minDate' => null, 'maxDate' => null, 'enableSeconds' => false, 'locale' => 'vi'])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($hasError = ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($name))
@php($dateFormat = $enableSeconds ? 'Y-m-d H:i:S' : 'Y-m-d H:i')

@once('sag-form-datetime-assets')
    @push('style')
        <link rel="stylesheet" href="{{ asset('sag/plugins/flatpickr/flatpickr.min.css') }}">
    @endpush
    @push('script')
        <script src="{{ asset('sag/plugins/flatpickr/flatpickr.min.js') }}"></script>
        <script src="{{ asset('sag/plugins/flatpickr/l10n/vi.js') }}"></script>
        <script src="{{ asset('sag/dist/js/sag-form.js') }}"></script>
    @endpush
@endonce

<x-sag-form.field :name="$name" :label="$label" :id="$fieldId" :help="$help">
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ old($name, $value) }}"
        data-sag-flatpickr="datetime"
        data-minute-increment="{{ $minuteIncrement }}"
        data-min-date="{{ $minDate }}"
        data-max-date="{{ $maxDate }}"
        data-enable-seconds="{{ $enableSeconds ? 'true' : 'false' }}"
        data-locale="{{ $locale }}"
        data-date-format="{{ $dateFormat }}"
        {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
    >
</x-sag-form.field>
