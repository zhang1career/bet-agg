<div class="mb-3">
    <label class="form-label" for="{{ $fieldId }}">{{ __('console.game_subjects.label_info') }}</label>
    <textarea name="info" id="{{ $fieldId }}" class="form-control mall-rich-text" rows="6">{{ old('info', $info ?? '') }}</textarea>
    <div class="form-text">{{ __('console.game_subjects.info_help') }}</div>
</div>
