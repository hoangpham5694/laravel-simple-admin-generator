<?php

namespace HoangPhamDev\SimpleAdminGenerator\Http\Requests;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:admin_menu_items,id'],
            'key' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'link_type' => ['required', Rule::in(AdminMenuLinkType::values())],
            'target' => ['nullable', 'string', 'max:2048'],
            'parameters' => ['nullable', 'array'],
            'permission' => ['nullable', 'string', 'max:255'],
            'target_window' => ['required', Rule::in(['_self', '_blank'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $parameters = $this->input('parameters');
        if (is_string($parameters)) {
            $parameters = trim($parameters);

            if ($parameters === '') {
                $parameters = null;
            } else {
                $decoded = json_decode($parameters, true);
                $parameters = json_last_error() === JSON_ERROR_NONE
                    ? $decoded
                    : $parameters;
            }
        }

        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
            'key' => $this->filled('key') ? trim((string) $this->input('key')) : null,
            'icon' => $this->filled('icon') ? trim((string) $this->input('icon')) : null,
            'target' => $this->filled('target') ? trim((string) $this->input('target')) : null,
            'parameters' => $parameters,
            'permission' => $this->filled('permission')
                ? trim((string) $this->input('permission'))
                : null,
            'target_window' => $this->input('target_window', '_self'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('link_type')) {
                return;
            }

            $type = AdminMenuLinkType::tryFrom((string) $this->input('link_type'));
            $target = (string) $this->input('target', '');

            if ($type === AdminMenuLinkType::None) {
                if ($target !== '') {
                    $validator->errors()->add('target', 'Target must be empty for a group menu.');
                }

                return;
            }

            if ($target === '') {
                $validator->errors()->add('target', 'Target is required for this link type.');

                return;
            }

            match ($type) {
                AdminMenuLinkType::Route => $this->validateRoute($validator, $target),
                AdminMenuLinkType::RootPath => $this->validateRootPath($validator, $target),
                AdminMenuLinkType::AdminPath => $this->validateAdminPath($validator, $target),
                AdminMenuLinkType::Url => $this->validateUrl($validator, $target),
                default => null,
            };
        });
    }

    private function validateRoute(Validator $validator, string $target): void
    {
        if (!Route::has($target)) {
            $validator->errors()->add('target', 'The selected Laravel route does not exist.');
        }
    }

    private function validateRootPath(Validator $validator, string $target): void
    {
        if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
            $validator->errors()->add(
                'target',
                'A root path must start with one slash and must not contain a host.'
            );
        }
    }

    private function validateAdminPath(Validator $validator, string $target): void
    {
        if (
            str_starts_with($target, '/')
            || parse_url($target, PHP_URL_SCHEME) !== null
            || parse_url($target, PHP_URL_HOST) !== null
        ) {
            $validator->errors()->add(
                'target',
                'An admin path must be relative and must not contain a scheme or host.'
            );
        }
    }

    private function validateUrl(Validator $validator, string $target): void
    {
        $scheme = parse_url($target, PHP_URL_SCHEME);
        if (
            filter_var($target, FILTER_VALIDATE_URL) === false
            || !in_array($scheme, ['http', 'https'], true)
        ) {
            $validator->errors()->add('target', 'The direct URL must use HTTP or HTTPS.');
        }
    }
}
