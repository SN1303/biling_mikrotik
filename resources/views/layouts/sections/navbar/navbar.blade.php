@php
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? '');
$currentUser = Auth::user();
@endphp

<!-- Navbar -->
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="{{ $containerNav }} d-flex align-items-center justify-content-between">

    <!-- Left: Menu Toggle + Status Indicator -->
    <div class="d-flex align-items-center gap-3">
      <!-- Menu Toggle (Mobile & Desktop Collapse) -->
      <div class="layout-menu-toggle navbar-nav align-items-xl-center me-1">
        <a class="nav-item nav-link px-0 me-xl-2 text-secondary" href="javascript:void(0)" title="Toggle Menu">
          <i class="mdi mdi-menu mdi-24px"></i>
        </a>
      </div>

      <!-- Network Status Pill -->
      <div class="navbar-status-pill d-none d-sm-inline-flex align-items-center">
        <span class="status-dot status-dot-pulse"></span>
        <span class="d-none d-md-inline text-muted me-1">Status Sistem:</span>
        <span class="text-success fw-bold">ONLINE</span>
      </div>

      <!-- Quick Breadcrumb / Context Title -->
      <div class="d-none d-lg-flex align-items-center text-muted" style="font-size: 0.85rem;">
        <span class="mx-2" style="opacity: 0.4;">|</span>
        <i class="mdi mdi-shield-check-outline text-primary me-1"></i>
        <span class="fw-medium text-dark">MRYES ISP Management</span>
      </div>
    </div>

    <!-- Right: Quick Actions & User Dropdown -->
    <div class="navbar-nav-right d-flex align-items-center gap-2" id="navbar-collapse">
      
      <ul class="navbar-nav flex-row align-items-center ms-auto gap-2">

        {{-- Quick Links for Fast Navigation --}}
        @if($currentUser && $currentUser->hasRole(1, 2, 4))
        <li class="nav-item d-none d-md-block">
          <a href="{{ route('google-map.standalone') }}" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" title="Buka Peta Jaringan">
            <i class="mdi mdi-map-marker-radius"></i>
            <span>Peta ODP</span>
          </a>
        </li>
        @endif

        @if($currentUser && $currentUser->hasRole(1, 2, 3, 6))
        <li class="nav-item d-none d-md-block">
          <a href="{{ route('tagihan.index') }}" class="btn btn-sm btn-light border d-flex align-items-center gap-1 text-dark" title="Lihat Tagihan Aktif">
            <i class="mdi mdi-receipt-text-outline text-primary"></i>
            <span>Tagihan</span>
          </a>
        </li>
        @endif

        <!-- User Dropdown -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown ms-1">
          <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center gap-2 p-1 rounded-pill" href="javascript:void(0);" data-bs-toggle="dropdown" style="background: #f8fafc; border: 1px solid #e2e8f0;">
            <div class="avatar avatar-online" style="width: 34px; height: 34px;">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 100%; height: 100%; background: linear-gradient(135deg, #2563eb, #0ea5e9); font-size: 0.85rem;">
                {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
              </div>
            </div>
            <div class="d-none d-sm-block text-start pe-2" style="line-height: 1.1;">
              <div class="fw-semibold text-dark" style="font-size: 0.8125rem;">{{ Str::limit($currentUser->name ?? 'User', 14) }}</div>
              <small class="text-muted" style="font-size: 0.7rem;">{{ $currentUser->role_name ?? 'User' }}</small>
            </div>
            <i class="mdi mdi-chevron-down text-muted me-1"></i>
          </a>

          <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="min-width: 230px; border-radius: 12px;">
            <li>
              <div class="dropdown-item py-2">
                <div class="d-flex align-items-center">
                  <div class="flex-shrink-0 me-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 38px; height: 38px; background: linear-gradient(135deg, #2563eb, #0ea5e9); font-size: 0.95rem;">
                      {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
                    </div>
                  </div>
                  <div class="flex-grow-1 overflow-hidden">
                    <span class="fw-bold d-block text-dark text-truncate" style="font-size: 0.875rem;">
                      {{ $currentUser->name ?? '-' }}
                    </span>
                    <small class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">
                      {{ $currentUser->role_name ?? 'User' }}
                    </small>
                  </div>
                </div>
              </div>
            </li>
            <li>
              <div class="dropdown-divider my-1"></div>
            </li>

            @if ($currentUser)
              @if ($currentUser->isSuperAdmin())
              <li>
                <a class="dropdown-item py-2" href="{{ route('staff.index') }}">
                  <i class='mdi mdi-shield-account-outline text-primary me-2'></i>
                  <span class="align-middle">Manajemen Staf</span>
                </a>
              </li>
              <li>
                <a class="dropdown-item py-2" href="{{ route('setting.index') }}">
                  <i class='mdi mdi-cog-outline text-secondary me-2'></i>
                  <span class="align-middle">Pengaturan Sistem</span>
                </a>
              </li>
              <li>
                <a class="dropdown-item py-2" href="{{ route('settings.sync.index') }}">
                  <i class='mdi mdi-sync-circle text-info me-2'></i>
                  <span class="align-middle">Sinkronisasi Data</span>
                </a>
              </li>
              <li>
                <div class="dropdown-divider my-1"></div>
              </li>
              @endif

              <li>
                <a class="dropdown-item py-2 text-danger" href="{{ route('logout') }}"
                  onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                  <i class='mdi mdi-logout me-2'></i>
                  <span class="align-middle fw-medium">Keluar / Logout</span>
                </a>
              </li>
              <form method="POST" id="logout-form" action="{{ route('logout') }}" class="d-none">
                @csrf
              </form>
            @else
              <li>
                <a class="dropdown-item py-2" href="{{ route('login') }}">
                  <i class='mdi mdi-login me-2'></i>
                  <span class="align-middle">Login</span>
                </a>
              </li>
            @endif
          </ul>
        </li>
        <!--/ User Dropdown -->

      </ul>
    </div>
  </div>
</nav>
<!-- / Navbar -->
