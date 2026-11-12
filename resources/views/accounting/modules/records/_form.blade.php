@php
    $record = $record ?? null;
    $isEdit = (bool) $record;
@endphp

<form method="POST" action="{{ $isEdit ? route('accounting.modules.records.update', [$moduleKey, $record->id]) : route('accounting.modules.records.store', $moduleKey) }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row">
        @foreach($fields as $field)
            @php
                $name = $field['name'];
                $type = $field['type'];
                $value = old($name, $record ? data_get($record, $name) : ($field['default'] ?? ''));

                if ($value instanceof \Carbon\CarbonInterface) {
                    $value = $value->format('Y-m-d');
                }
            @endphp

            <div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }} mb-3">
                @if($type === 'checkbox')
                    <div class="form-check mt-4">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input id="{{ $name }}" name="{{ $name }}" value="1" type="checkbox" class="form-check-input @error($name) is-invalid @enderror" @checked((bool) $value)>
                        <label for="{{ $name }}" class="form-check-label">{{ $field['label'] }}</label>
                    </div>
                @else
                    <label for="{{ $name }}">{{ $field['label'] }}</label>

                    @if($type === 'select')
                        <select id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror">
                            @foreach($field['options'] as $optionValue => $label)
                                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif($type === 'textarea')
                        <textarea id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" rows="4">{{ $value }}</textarea>
                    @else
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" step="{{ $type === 'number' ? '0.01' : null }}" class="form-control @error($name) is-invalid @enderror" value="{{ $type === 'password' ? '' : $value }}">
                    @endif
                @endif

                @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">
            <i data-feather="save"></i> {{ $isEdit ? 'Update' : 'Create' }}
        </button>
        @if($isEdit)
            <a class="btn btn-outline-secondary" href="{{ $returnUrl ?? url()->previous() }}"><i data-feather="x"></i> Cancel</a>
        @endif
    </div>
</form>
