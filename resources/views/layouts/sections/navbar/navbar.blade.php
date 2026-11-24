@php
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? 'navbar-detached');
$currentUser = Auth::user();
@endphp

<!-- Navbar -->
@if(isset($navbarDetached) && $navbarDetached == 'navbar-detached')
<nav
  class="layout-navbar {{$containerNav}} navbar navbar-expand-xl {{$navbarDetached}} align-items-center bg-navbar-theme"
  id="layout-navbar">
@endif
@if(isset($navbarDetached) && $navbarDetached == '')
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="{{$containerNav}}">
@endif

    <!-- Menu Toggle (Mobile) -->
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
      <a class="nav-item nav-link px-0 me-xl-4 text-secondary" href="javascript:void(0)">
        <i class="mdi mdi-menu mdi-24px"></i>
      </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100" id="navbar-collapse">
      <!-- Right: Actions & User Profile -->
      <ul class="navbar-nav flex-row align-items-center ms-auto gap-2">

        @if($currentUser && $currentUser->hasRole(1, 2, 4))
        <li class="nav-item d-none d-md-block">
          <a href="{{ route('google-map.standalone') }}" target="_blank" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" title="Buka Peta Jaringan ODP">
            <i class="mdi mdi-map-marker-radius"></i>
            <span>Peta ODP</span>
          </a>
        </li>
        @endif

        @if($currentUser && $currentUser->hasRole(1, 2, 3, 6))
        <li class="nav-item d-none d-md-block">
          <a href="{{ route('tagihan.index') }}" class="btn btn-sm btn-light border d-flex align-items-center gap-1 text-dark" title="Lihat Tagihan">
            <i class="mdi mdi-receipt-text-outline text-primary"></i>
            <span>Tagihan</span>
          </a>
        </li>
        @endif

        <!-- Style Switcher (Light / Dark Mode) -->
        <li class="nav-item dropdown-style-switcher dropdown me-1">
          <a class="nav-link btn btn-text-secondary rounded-pill btn-icon dropdown-toggle hide-arrow d-flex align-items-center justify-content-center" href="javascript:void(0);" data-bs-toggle="dropdown" title="Ganti Mode Tema (Light/Dark)" style="width: 36px; height: 36px;">
            <i class="mdi {{ ($configData['style'] === 'dark') ? 'mdi-weather-night text-info' : 'mdi-weather-sunny text-warning' }} fs-4"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end dropdown-styles shadow-sm border py-1" style="border-radius: 12px; min-width: 140px;">
            <li>
              <a class="dropdown-item py-1.5 d-flex align-items-center {{ ($configData['style'] === 'light') ? 'active' : '' }}" href="javascript:void(0);" data-theme="light" onclick="setAppTheme('light')">
                <i class="mdi mdi-weather-sunny text-warning me-2 fs-5"></i>
                <span class="align-middle fw-semibold">Light</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item py-1.5 d-flex align-items-center {{ ($configData['style'] === 'dark') ? 'active' : '' }}" href="javascript:void(0);" data-theme="dark" onclick="setAppTheme('dark')">
                <i class="mdi mdi-weather-night text-info me-2 fs-5"></i>
                <span class="align-middle fw-semibold">Dark</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item py-1.5 d-flex align-items-center" href="javascript:void(0);" data-theme="system" onclick="setAppTheme('system')">
                <i class="mdi mdi-monitor text-secondary me-2 fs-5"></i>
                <span class="align-middle fw-semibold">System</span>
              </a>
            </li>
          </ul>
        </li>
        <!-- / Style Switcher -->

        <!-- User Dropdown -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown ms-1">
          <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center gap-2 p-1 rounded-pill" href="javascript:void(0);" data-bs-toggle="dropdown" style="background: var(--mryes-sidebar-user-bg); border: 1px solid var(--mryes-navbar-border);">
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

@if(!isset($navbarDetached))
  </div>
@endif
</nav>
<!-- / Navbar -->

{{-- setAppTheme() didefinisikan sekali di layouts/sections/scriptsIncludes.blade.php --}}
