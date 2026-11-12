<form method="GET" class="d-inline-flex align-items-center gap-2">
    <label for="countryPick" class="small fw-bold" style="color: var(--s-muted)"><i class="ri-global-line"></i> Prices for</label>
    <select id="countryPick" name="country" class="form-select" style="width: auto; border-radius: 12px; font-weight: 600" onchange="this.form.submit()">
        @foreach($countries as $c)<option value="{{ $c->code }}" @selected($c->code === $current)>{{ $c->flag }} {{ $c->name }}</option>@endforeach
    </select>
</form>
