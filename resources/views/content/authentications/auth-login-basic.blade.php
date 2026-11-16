@php
$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Login - MRYES Billing')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/@form-validation/umd/styles/index.min.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
<style>
  .auth-card-modern {
    border-radius: 18px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04) !important;
  }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/@form-validation/umd/bundle/popular.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-bootstrap5/index.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/@form-validation/umd/plugin-auto-focus/index.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/pages-auth.js')}}"></script>
@endsection

@section('content')
<div class="position-relative">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner py-4" style="max-width: 440px;">

      <!-- Login Card -->
      <div class="card auth-card-modern p-2 p-sm-3">
        <!-- Logo -->
        <div class="app-brand justify-content-center mt-3 mb-2 border-0 bg-transparent">
          <a href="{{url('/')}}" class="app-brand-link gap-2 d-flex align-items-center text-decoration-none">
            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #2563eb, #0ea5e9);">
              <i class="mdi mdi-access-point-network text-white" style="font-size: 24px;"></i>
            </div>
            <div class="text-start">
              <span class="app-brand-text fw-bold text-dark d-block" style="font-size: 1.25rem; letter-spacing: -0.02em;">MRYES NET</span>
              <small class="text-muted fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.08em; text-transform: uppercase;">BILLING & MANAGEMENT</small>
            </div>
          </a>
        </div>
        <!-- /Logo -->

        <div class="card-body pt-2">
          <h4 class="mb-1 text-dark fw-bold text-center" style="letter-spacing: -0.02em;">Masuk ke Sistem</h4>
          <p class="mb-4 text-muted text-center small">Silakan masukkan email atau username staf untuk mengakses dashboard.</p>

          @if (session('error'))
          <div class="alert alert-danger d-flex align-items-center p-2 mb-3" role="alert" style="border-radius: 10px; font-size: 0.85rem;">
            <i class="mdi mdi-alert-circle-outline me-2 fs-5"></i>
            <div>{{ session('error') }}</div>
          </div>
          @endif

          <form id="formAuthentication" class="mb-3" action="{{ route('auth.login') }}" method="POST">
            @csrf
            <div class="form-floating form-floating-outline mb-3">
              <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="Email atau username" required autofocus>
              <label for="email">Email atau Username</label>
              @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <div class="form-password-toggle">
                <div class="input-group input-group-merge">
                  <div class="form-floating form-floating-outline">
                    <input type="password" id="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Password akun" required aria-describedby="password" />
                    <label for="password">Password</label>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                  </div>
                  <span class="input-group-text cursor-pointer"><i class="mdi mdi-eye-off-outline"></i></span>
                </div>
              </div>
            </div>

            <div class="mb-3 d-flex justify-content-between align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember-me" name="remember">
                <label class="form-check-label small" for="remember-me">
                  Ingat saya
                </label>
              </div>
            </div>

            <div class="mb-3">
              <button class="btn btn-primary d-grid w-100 py-2 fs-6 fw-bold" type="submit">
                <i class="mdi mdi-login me-1"></i> Masuk Sekarang
              </button>
            </div>
          </form>

          <div class="text-center pt-2 border-top">
            <small class="text-muted" style="font-size: 0.75rem;">
              <i class="mdi mdi-shield-lock-outline me-1"></i>Akses khusus operator & staf internal MRYES NET
            </small>
          </div>
        </div>
      </div>
      <!-- /Login Card -->

    </div>
  </div>
</div>
@endsection
