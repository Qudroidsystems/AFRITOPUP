<form method="POST" action="{{ $school ? route('admin.schools.update', $school) : route('admin.schools.store') }}" class="row g-2">
    @csrf @if($school) @method('PUT') @endif
    <div class="col-md-8"><input class="form-control form-control-sm" name="name" value="{{ $school?->name }}" placeholder="School name" required></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="code" value="{{ $school?->code }}" placeholder="Short code" required></div>
    <div class="col-md-6"><select class="form-select form-select-sm" name="country_code">@foreach($countries as $c)<option value="{{ $c->code }}" @selected(($school?->country_code ?? 'NG') === $c->code)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><input class="form-control form-control-sm" name="city" value="{{ $school?->city }}" placeholder="City"></div>
    <div class="col-md-6"><input class="form-control form-control-sm" name="contact_email" value="{{ $school?->contact_email }}" placeholder="Bursar e-mail"></div>
    <div class="col-md-6"><input class="form-control form-control-sm" name="contact_phone" value="{{ $school?->contact_phone }}" placeholder="Phone"></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="bank_name" value="{{ $school?->bank_name }}" placeholder="Bank"></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="account_number" value="{{ $school?->account_number }}" placeholder="Account no."></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="account_name" value="{{ $school?->account_name }}" placeholder="Account name"></div>
    <div class="col-8"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($school?->is_active ?? true) id="sa{{ $school?->id ?? 'new' }}"><label class="form-check-label small" for="sa{{ $school?->id ?? 'new' }}">Parents can pay this school</label></div></div>
    <div class="col-4"><button class="btn btn-sm btn-primary w-100">Save</button></div>
</form>
