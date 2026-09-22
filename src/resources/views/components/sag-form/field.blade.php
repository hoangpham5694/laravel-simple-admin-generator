@props(['name', 'label' => null, 'id' => null, 'help' => null])

@php($fieldId = $id ?: trim((string) preg_replace('/[.\[\]]+/', '_', $name), '_'))
@php($errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag)
@php($hasError = $errorBag->has($name))

<div {{ $attributes->class(['form-group']) }}>
    @if ($label !== null)
        <label for="{{ $fieldId }}">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($help !== null)
        <small class="form-text text-muted">{{ $help }}</small>
    @endif

    @if ($hasError)
        <div class="invalid-feedback">{{ $errorBag->first($name) }}</div>
    @endif
</div>
