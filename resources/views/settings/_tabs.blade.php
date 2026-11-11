<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('portal-settings.payments') ? 'active' : '' }}" href="{{ route('portal-settings.payments') }}"><i class="ri-bank-card-line me-1"></i>Payment gateways</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('portal-settings.providers') ? 'active' : '' }}" href="{{ route('portal-settings.providers') }}"><i class="ri-plug-line me-1"></i>VTU &amp; SMS providers</a></li>
</ul>
