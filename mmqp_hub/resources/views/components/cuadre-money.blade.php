@props(['field', 'label', 'value' => '0.00'])
@php($fieldId = str_replace('.', '-', $field))
<div>
    <label for="{{ $fieldId }}" class="block text-sm font-medium mb-1">{{ $label }}</label>
    <input type="number" id="{{ $fieldId }}" name="{{ $field === 'blisters.total' ? 'blisters[total]' : $field }}"
        value="{{ old($field, $value) }}" required min="0" max="99999999.99" step="0.01"
        aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="error-{{ $fieldId }}"
        class="calc-input w-full rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-green-500 text-right p-2">
    <p id="error-{{ $fieldId }}" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $errors->first($field) }}</p>
</div>
