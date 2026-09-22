@props(['name', 'label' => null, 'id' => null, 'value' => 1, 'checked' => false, 'inline' => false, 'help' => null, 'uncheckedValue' => null])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag)
@php($hasError = $errorBag->has($name))
@php($checkedValue = old($name, $checked ? $value : ''))
@php($isChecked = (string) $checkedValue === (string) $value)

<div class="form-group">
    <div @class(['form-check', 'form-check-inline' => $inline])>
        @if ($uncheckedValue !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
        @endif
        <input type="checkbox" name="{{ $name }}" id="{{ $fieldId }}" value="{{ $value }}" @checked($isChecked) {{ $attributes->class(['form-check-input', 'is-invalid' => $hasError]) }}>
        @if ($label !== null)
            <label class="form-check-label" for="{{ $fieldId }}">{{ $label }}</label>
        @endif
    </div>
    @if ($help !== null)<small class="form-text text-muted">{{ $help }}</small>@endif
    @if ($hasError)<div class="invalid-feedback d-block">{{ $errorBag->first($name) }}</div>@endif
</div>
