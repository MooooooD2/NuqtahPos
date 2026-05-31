{{-- FILE: resources/views/settings/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.settings'))
@section('page-title', __('pos.settings'))

@push('styles')
    <style @nonce>
        .settings-nav .nav-link {
            color: #64748b;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .settings-nav .nav-link.active {
            background: #3b82f6;
            color: #fff;
        }

        .settings-nav .nav-link i {
            width: 20px;
        }

        .setting-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .setting-card-header {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }

        .setting-row {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-label {
            flex: 1;
        }

        .setting-label .label-text {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .setting-label .label-desc {
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .setting-control {
            flex: 0 0 220px;
        }

        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.3em;
            cursor: pointer;
        }

        .save-btn-wrapper {
            position: sticky;
            bottom: 1rem;
            z-index: 99;
        }

        @media (max-width: 768px) {
            .setting-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .setting-control {
                flex: 1;
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row g-4">
        {{-- Sidebar Nav --}}
        <div class="col-md-3">
            <div class="card">
                <div class="card-body p-2">
                    <nav class="settings-nav nav flex-column">
                        <button class="nav-link active text-start" data-tab="general" data-fn="showTab" data-args='["general"]'>
                            <i class="fas fa-store me-2"></i>{{ __('pos.general_settings') }}
                        </button>
                        <button class="nav-link text-start" data-tab="tax" data-fn="showTab" data-args='["tax"]'>
                            <i class="fas fa-percent me-2"></i>{{ __('pos.tax_settings') }}
                        </button>
                        <button class="nav-link text-start" data-tab="invoice" data-fn="showTab" data-args='["invoice"]'>
                            <i class="fas fa-file-invoice me-2"></i>{{ __('pos.invoice_settings') }}
                        </button>
                        <button class="nav-link text-start" data-tab="pos" data-fn="showTab" data-args='["pos"]'>
                            <i class="fas fa-cash-register me-2"></i>{{ __('pos.pos_settings') }}
                        </button>
                        <button class="nav-link text-start" data-tab="roles" data-fn="showTab" data-args='["roles"]'>
                            <i class="fas fa-shield-alt me-2"></i>{{ __('pos.roles_permissions') }}
                        </button>
                        <button class="nav-link text-start" data-tab="treasury" data-fn="showTab" data-args='["treasury"]'>
                            <i class="fas fa-vault me-2"></i>{{ __('pos.treasury_link') }}
                        </button>
                        <button class="nav-link text-start" data-tab="financial" data-fn="showTab" data-args='["financial"]'>
                            <i class="fas fa-chart-line me-2"></i>{{ app()->getLocale() === 'ar' ? 'الربحية' : 'Profitability' }}
                        </button>
                        <button class="nav-link text-start" data-tab="printing" data-fn="showTab" data-args='["printing"]'>
                            <i class="fas fa-print me-2"></i>{{ app()->getLocale() === 'ar' ? 'الطباعة الحرارية' : 'Thermal Printing' }}
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        {{-- Settings Panels --}}
        <div class="col-md-9">

            {{-- General Settings --}}
            <div id="tab-general" class="settings-tab">
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-store me-2 text-primary"></i>{{ __('pos.general_settings') }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.store_name') }}</div>
                            <div class="label-desc">اسم المتجر الذي يظهر في الفاتورة</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="store_name" id="s_store_name">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.store_address') }}</div>
                            <div class="label-desc">عنوان المتجر في الفاتورة</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="store_address" id="s_store_address">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.store_phone') }}</div>
                            <div class="label-desc">رقم هاتف المتجر</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="store_phone" id="s_store_phone">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.store_email') }}</div>
                            <div class="label-desc">البريد الإلكتروني للمتجر</div>
                        </div>
                        <div class="setting-control">
                            <input type="email" class="form-control" data-key="store_email" id="s_store_email">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.currency') }}</div>
                            <div class="label-desc">رمز العملة المستخدمة (EGP, USD, SAR...)</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="currency" id="s_currency" maxlength="5">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.currency_symbol') }}</div>
                            <div class="label-desc">رمز العملة المعروض (ج.م، $، ريال...)</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="currency_symbol" id="s_currency_symbol"
                                maxlength="10">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.default_language') }}</div>
                            <div class="label-desc">اللغة الافتراضية عند تسجيل الدخول</div>
                        </div>
                        <div class="setting-control">
                            <select class="form-select" data-key="default_language" id="s_default_language">
                                <option value="ar">العربية</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tax Settings --}}
            <div id="tab-tax" class="settings-tab d-none">
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-percent me-2 text-warning"></i>{{ __('pos.tax_settings') }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.tax_enabled') }}</div>
                            <div class="label-desc">تفعيل حساب الضريبة على الفواتير</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="tax_enabled"
                                    id="s_tax_enabled" data-on-change="toggleTaxFields">
                            </div>
                        </div>
                    </div>

                    <div id="taxFields">
                        <div class="setting-row">
                            <div class="setting-label">
                                <div class="label-text">{{ __('pos.tax_rate') }}</div>
                                <div class="label-desc">نسبة الضريبة المضافة على الفاتورة (مثال: 14 للـ VAT)</div>
                            </div>
                            <div class="setting-control">
                                <div class="input-group">
                                    <input type="number" class="form-control" data-key="tax_rate" id="s_tax_rate"
                                        min="0" max="100" step="0.01">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="setting-row">
                            <div class="setting-label">
                                <div class="label-text">{{ __('pos.tax_name') }} (عربي)</div>
                                <div class="label-desc">اسم الضريبة يظهر في الفاتورة بالعربية</div>
                            </div>
                            <div class="setting-control">
                                <input type="text" class="form-control" data-key="tax_name_ar" id="s_tax_name_ar">
                            </div>
                        </div>

                        <div class="setting-row">
                            <div class="setting-label">
                                <div class="label-text">{{ __('pos.tax_name') }} (English)</div>
                                <div class="label-desc">Tax name shown on invoice in English</div>
                            </div>
                            <div class="setting-control">
                                <input type="text" class="form-control" data-key="tax_name_en" id="s_tax_name_en">
                            </div>
                        </div>

                        <div class="setting-row">
                            <div class="setting-label">
                                <div class="label-text">{{ __('pos.tax_inclusive') }}</div>
                                <div class="label-desc">إذا فُعِّل، فالسعر يشمل الضريبة. إذا أُوقف، تُضاف الضريبة على السعر
                                </div>
                            </div>
                            <div class="setting-control">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" data-key="tax_inclusive"
                                        id="s_tax_inclusive">
                                </div>
                            </div>
                        </div>

                        <div class="setting-row">
                            <div class="setting-label">
                                <div class="label-text">{{ __('pos.tax_number') }}</div>
                                <div class="label-desc">الرقم الضريبي للمتجر</div>
                            </div>
                            <div class="setting-control">
                                <input type="text" class="form-control" data-key="tax_number" id="s_tax_number">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tax Preview --}}
                <div class="card border-info" id="taxPreview" class="u-hidden">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-eye me-2"></i>معاينة حساب الضريبة / Tax Calculation Preview
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">سعر المنتج / Product Price</label>
                                <input type="number" class="form-control" id="previewPrice" value="100"
                                    data-on-input="updateTaxPreview">
                            </div>
                            <div class="col-md-8">
                                <div class="bg-light rounded p-3 mt-3" id="taxPreviewResult">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-muted small">قبل الضريبة</div>
                                            <div class="fw-bold" id="previewBefore">-</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">الضريبة</div>
                                            <div class="fw-bold text-warning" id="previewTax">-</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">الإجمالي</div>
                                            <div class="fw-bold text-success" id="previewTotal">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Invoice Settings --}}
            <div id="tab-invoice" class="settings-tab d-none">
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-file-invoice me-2 text-success"></i>{{ __('pos.invoice_settings') }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.invoice_prefix') }}</div>
                            <div class="label-desc">بادئة رقم الفاتورة (مثال: INV سيعطي INV-20240101-0001)</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="invoice_prefix" id="s_invoice_prefix"
                                maxlength="10">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.invoice_footer') }}</div>
                            <div class="label-desc">نص يظهر في أسفل الفاتورة المطبوعة</div>
                        </div>
                        <div class="setting-control">
                            <textarea class="form-control" data-key="invoice_footer" id="s_invoice_footer" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.show_tax_invoice') }}</div>
                            <div class="label-desc">إظهار سطر الضريبة في الفاتورة المطبوعة</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="show_tax_invoice"
                                    id="s_show_tax_invoice">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.auto_print') }}</div>
                            <div class="label-desc">طباعة الفاتورة تلقائياً بعد إتمام البيع</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="auto_print" id="s_auto_print">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- POS Settings --}}
            <div id="tab-pos" class="settings-tab d-none">
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-cash-register me-2 text-danger"></i>{{ __('pos.pos_settings') }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.default_payment') }}</div>
                            <div class="label-desc">طريقة الدفع المحددة افتراضياً عند فتح نقطة البيع</div>
                        </div>
                        <div class="setting-control">
                            <select class="form-select" data-key="default_payment" id="s_default_payment">
                                <option value="cash">{{ __('pos.cash') }}</option>
                                <option value="card">{{ __('pos.card') }}</option>
                                <option value="transfer">{{ __('pos.transfer') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.pos_sound') }}</div>
                            <div class="label-desc">صوت تنبيه عند مسح الباركود بنجاح</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="pos_sound" id="s_pos_sound">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.low_stock_alert') }}</div>
                            <div class="label-desc">تنبيه الكاشير عند بيع منتج منخفض المخزون</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="low_stock_alert"
                                    id="s_low_stock_alert">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.allow_negative_stock') }}</div>
                            <div class="label-desc">السماح بإتمام البيع حتى لو نفذ المخزون</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="allow_negative_stock"
                                    id="s_allow_negative_stock">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Roles & Permissions Settings --}}
            <div id="tab-roles" class="settings-tab d-none">

                {{-- Users Management --}}
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users me-2"></i>{{ __('pos.users') }}</span>
                        <button class="btn btn-sm btn-success" data-fn="showUserModal">
                            <i class="fas fa-plus me-1"></i>{{ __('pos.add_user') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>{{ __('pos.full_name') }}</th>
                                        <th>{{ __('pos.username') }}</th>
                                        <th>{{ __('pos.role') }}</th>
                                        <th>{{ __('pos.status') }}</th>
                                        <th>{{ __('pos.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('pos.loading') }}...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    {{-- Roles Management --}}
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-users me-2"></i>{{ __('pos.roles') }}</span>
                                <button class="btn btn-sm btn-primary" data-fn="showCreateRoleModal">
                                    <i class="fas fa-plus me-1"></i>{{ __('pos.create_role') }}
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>{{ __('pos.role_name') }}</th>
                                                <th>{{ __('pos.guard_name') }}</th>
                                                <th>{{ __('pos.permissions') }}</th>
                                                <th>{{ __('pos.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody id="rolesTableBody">
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">
                                                    {{ __('pos.loading') }}...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Users Role Assignment --}}
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-user-tag me-2"></i>{{ __('pos.assign_role') }}
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">{{ __('pos.select_user') }}</label>
                                    <select class="form-select" id="userSelect">
                                        <option value="">{{ __('pos.select_user') }}</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('pos.select_role') }}</label>
                                    <select class="form-select" id="roleSelect">
                                        <option value="">{{ __('pos.select_role') }}</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary w-100" data-fn="assignRoleToUser">
                                    <i class="fas fa-save me-2"></i>{{ __('pos.assign_role') }}
                                </button>
                            </div>
                        </div>

                        {{-- Current User Roles --}}
                        <div class="card mt-3">
                            <div class="card-header">
                                <i class="fas fa-user-check me-2"></i>{{ __('pos.current_roles') }}
                            </div>
                            <div class="card-body" id="userRolesInfo">
                                <p class="text-muted text-center mb-0">{{ __('pos.select_user') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Permissions Table for Selected Role --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="fas fa-key me-2"></i>{{ __('pos.permissions') }} - <span id="selectedRoleName">Select a
                            role</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="row" id="permissionsGrid">
                                <div class="col-12 text-center text-muted py-3">{{ __('pos.select_role') }}</div>
                            </div>
                        </div>
                        <button class="btn btn-success" data-fn="savePermissions" id="savePermBtn"
                            class="u-hidden">
                            <i class="fas fa-save me-2"></i>{{ __('pos.save') }}
                        </button>
                    </div>
                </div>
            </div>

                </div>{{-- /row roles+assign --}}

            {{-- Add / Edit User Modal --}}
            <div class="modal fade" id="userModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="userModalTitle">{{ __('pos.add_user') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editUserId">
                            <div class="mb-3">
                                <label class="form-label">{{ __('pos.full_name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="userFullName">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('pos.username') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="userUsername" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" id="userPasswordLabel">
                                    {{ __('pos.password') }} <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="userPassword" autocomplete="new-password">
                                <div class="form-text" id="userPasswordHint"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('pos.role') }} <span class="text-danger">*</span></label>
                                <select class="form-select" id="userRole">
                                    <option value="">{{ __('pos.select_role') }}</option>
                                </select>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="userIsActive" checked>
                                <label class="form-check-label" for="userIsActive">{{ __('pos.active') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                            <button type="button" class="btn btn-primary" data-fn="saveUser">
                                <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Create/Edit Role Modal --}}
            <div class="modal fade" id="roleModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="roleModalTitle">{{ __('pos.create_role') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editRoleId">
                            <div class="mb-3">
                                <label class="form-label">{{ __('pos.role_name') }}</label>
                                <input type="text" class="form-control" id="roleName"
                                    placeholder="e.g., supervisor">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('pos.guard_name') }}</label>
                                <select class="form-select" id="guardName">
                                    <option value="web">web</option>
                                    <option value="api">api</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                            <button type="button" class="btn btn-primary"
                                data-fn="saveRole">{{ __('pos.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Treasury Settings --}}
            <div id="tab-treasury" class="settings-tab d-none">
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-vault me-2 text-success"></i>{{ __('pos.treasury_link') }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.max_daily_withdrawal') }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'الحد الأقصى للمبلغ المسحوب يومياً من الخزينة (0 = بلا حد)' : 'Maximum cash withdrawal per day (0 = unlimited)' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="input-group">
                                <input type="number" class="form-control" data-key="max_daily_withdrawal" id="s_max_daily_withdrawal" min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ \App\Models\Setting::get('currency_symbol', 'ج.م') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.min_cash_balance') }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'تنبيه عند انخفاض الرصيد النقدي عن هذا الحد (0 = لا تنبيه)' : 'Alert when cash balance drops below this threshold (0 = disabled)' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="input-group">
                                <input type="number" class="form-control" data-key="min_cash_balance" id="s_min_cash_balance" min="0" step="0.01" placeholder="0">
                                <span class="input-group-text">{{ \App\Models\Setting::get('currency_symbol', 'ج.م') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-book me-2 text-primary"></i>{{ app()->getLocale() === 'ar' ? 'الربط المحاسبي — تلقائي عند إغلاق الجلسة' : 'Accounting Link — Auto Journal on Session Close' }}
                    </div>

                    <div class="alert alert-info mx-3 mt-2 py-2 small">
                        <i class="fas fa-info-circle me-1"></i>
                        {{ app()->getLocale() === 'ar'
                            ? 'عند إغلاق جلسة الكاشير، سيتم تلقائياً إنشاء قيد محاسبي في حساب الخزينة وحساب الإيرادات المحددَين أدناه. اتركهما فارغَين لتعطيل الربط.'
                            : 'When a cashier session is closed, a journal entry is automatically posted to the accounts below. Leave blank to disable.' }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.cash_account_code') }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'كود حساب النقدية في دفتر الأستاذ (مثال: 1001)' : 'Cash/Treasury account code in chart of accounts (e.g. 1001)' }}</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="cash_account_code" id="s_cash_account_code" placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: 1001' : 'e.g. 1001' }}">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ __('pos.revenue_account_code') }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'كود حساب الإيرادات في دفتر الأستاذ (مثال: 4001)' : 'Revenue account code in chart of accounts (e.g. 4001)' }}</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="revenue_account_code" id="s_revenue_account_code" placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: 4001' : 'e.g. 4001' }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Financial / Profit Settings --}}
            <div id="tab-financial" class="settings-tab d-none">
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-chart-line me-2 text-success"></i>{{ app()->getLocale() === 'ar' ? 'إعدادات الربحية والأداء' : 'Profitability & Performance Settings' }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'نسبة هامش الربح المستهدفة %' : 'Target Profit Margin %' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'ينبه تقرير صافي الربح ولوحة الأداء عند الانخفاض عن هذه النسبة (0 = معطّل)' : 'Alerts in net profit report and KPI dashboard when margin drops below this (0 = disabled)' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="input-group">
                                <input type="number" class="form-control" data-key="profit_margin_target" id="s_profit_margin_target" min="0" max="100" step="0.1" placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'السماح للكاشير بتغيير سعر البيع' : 'Allow Cashier to Change Item Price' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'عند التعطيل، لا يمكن للكاشير تعديل سعر المنتج في الفاتورة — يتطلب صلاحية مدير' : 'When disabled, cashier cannot override item price — requires manager permission' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="allow_cashier_price_change" id="s_allow_cashier_price_change">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════
                 Thermal Printing Tab
            ════════════════════════════════════════════════════════════════ --}}
            <div id="tab-printing" class="settings-tab d-none">

                {{-- Auto-Print Triggers --}}
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-bolt me-2 text-warning"></i>
                        {{ app()->getLocale() === 'ar' ? 'التشغيل التلقائي' : 'Auto-Print Triggers' }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'طباعة تلقائية عند البيع' : 'Auto Print on Sale' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'يُطبع الإيصال فور إتمام أي فاتورة' : 'Automatically print receipt after every sale' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="print_on_sale" id="s_print_on_sale">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'طباعة تلقائية عند المرتجع' : 'Auto Print on Return' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'يُطبع إشعار المرتجع فور معالجته' : 'Automatically print return receipt after processing' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="print_on_return" id="s_print_on_return">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'طباعة تقرير الوردية عند الإغلاق' : 'Print Shift Report on Close' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'يُطبع تقرير نهاية الوردية تلقائياً عند إغلاق الجلسة' : 'Automatically print shift report when session is closed' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="print_on_shift_close" id="s_print_on_shift_close">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Receipt Options --}}
                <div class="setting-card">
                    <div class="setting-card-header">
                        <i class="fas fa-receipt me-2 text-success"></i>
                        {{ app()->getLocale() === 'ar' ? 'إعدادات الإيصال' : 'Receipt Options' }}
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'عدد نسخ الإيصال' : 'Receipt Copies' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'كم نسخة تُطبع من كل فاتورة' : 'Number of copies printed per receipt' }}</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" class="form-control" data-key="receipt_copies" id="s_receipt_copies" min="1" max="10" value="1">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'قالب الإيصال' : 'Receipt Template' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'تصميم الإيصال المطبوع' : 'Layout design of the printed receipt' }}</div>
                        </div>
                        <div class="setting-control">
                            <select class="form-select" data-key="receipt_template" id="s_receipt_template">
                                <option value="default">{{ app()->getLocale() === 'ar' ? 'افتراضي' : 'Default' }}</option>
                                <option value="compact">{{ app()->getLocale() === 'ar' ? 'مضغوط' : 'Compact' }}</option>
                                <option value="detailed">{{ app()->getLocale() === 'ar' ? 'تفصيلي' : 'Detailed' }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'إظهار QR كود الضريبة' : 'Show ETA QR Code' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'طباعة QR كود منظومة الفاتورة الإلكترونية المصرية' : 'Print Egyptian Tax Authority QR code on receipt' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="receipt_show_qr" id="s_receipt_show_qr">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'إظهار باركود على الإيصال' : 'Show Barcode on Receipt' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'طباعة باركود رقم الفاتورة أسفل الإيصال' : 'Print invoice number as barcode at receipt bottom' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="receipt_show_barcode" id="s_receipt_show_barcode">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'الطباعة عبر المتصفح عند الفشل' : 'Browser Fallback on Failure' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'عند فشل الطابعة الحرارية، افتح نافذة طباعة المتصفح' : 'Open browser print dialog when thermal printer fails' }}</div>
                        </div>
                        <div class="setting-control">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" data-key="print_fallback_browser" id="s_print_fallback_browser">
                            </div>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <div class="label-text">{{ app()->getLocale() === 'ar' ? 'الرقم الضريبي على الإيصال' : 'Tax Registration Number' }}</div>
                            <div class="label-desc">{{ app()->getLocale() === 'ar' ? 'يظهر في رأس الإيصال بجانب اسم المتجر' : 'Displayed in receipt header next to store name' }}</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" class="form-control" data-key="tax_registration_number" id="s_tax_registration_number"
                                placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: 123456789' : 'e.g. 123456789' }}">
                        </div>
                    </div>
                </div>

                {{-- Printers Management --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-print me-2"></i>{{ app()->getLocale() === 'ar' ? 'الطابعات المتصلة' : 'Connected Printers' }}</span>
                        <button class="btn btn-sm btn-success" data-fn="showPrinterModal">
                            <i class="fas fa-plus me-1"></i>{{ app()->getLocale() === 'ar' ? 'إضافة طابعة' : 'Add Printer' }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>{{ app()->getLocale() === 'ar' ? 'الاسم' : 'Name' }}</th>
                                        <th>{{ app()->getLocale() === 'ar' ? 'الاتصال' : 'Connection' }}</th>
                                        <th>{{ app()->getLocale() === 'ar' ? 'العرض' : 'Paper' }}</th>
                                        <th>{{ app()->getLocale() === 'ar' ? 'الفرع' : 'Branch' }}</th>
                                        <th>{{ app()->getLocale() === 'ar' ? 'الحالة' : 'Status' }}</th>
                                        <th>{{ app()->getLocale() === 'ar' ? 'إجراءات' : 'Actions' }}</th>
                                    </tr>
                                </thead>
                                <tbody id="printersTableBody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">{{ __('pos.loading') }}...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Queue Stats --}}
                <div class="row g-3 mt-1" id="printQueueStats" style="display:none!important">
                    <div class="col-6 col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body py-2">
                                <div class="h4 mb-0 text-warning" id="qs_pending">-</div>
                                <div class="small text-muted">{{ app()->getLocale() === 'ar' ? 'معلّقة' : 'Pending' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center border-danger">
                            <div class="card-body py-2">
                                <div class="h4 mb-0 text-danger" id="qs_failed">-</div>
                                <div class="small text-muted">{{ app()->getLocale() === 'ar' ? 'فاشلة' : 'Failed' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body py-2">
                                <div class="h4 mb-0 text-info" id="qs_processing">-</div>
                                <div class="small text-muted">{{ app()->getLocale() === 'ar' ? 'قيد التنفيذ' : 'Processing' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body py-2">
                                <div class="h4 mb-0 text-success" id="qs_completed_today">-</div>
                                <div class="small text-muted">{{ app()->getLocale() === 'ar' ? 'مكتملة اليوم' : 'Done Today' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- /tab-printing --}}

            {{-- ── Printer Add/Edit Modal ───────────────────────────────────── --}}
            <div class="modal fade" id="printerModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="printerModalTitle">
                                {{ app()->getLocale() === 'ar' ? 'إضافة طابعة' : 'Add Printer' }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editPrinterId">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'اسم الطابعة' : 'Printer Name' }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="p_name" placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: طابعة الكاشير' : 'e.g. Cashier Printer' }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'نوع الاتصال' : 'Connection Type' }} <span class="text-danger">*</span></label>
                                    <select class="form-select" id="p_connection_type" data-on-change="togglePrinterFields">
                                        <option value="network">{{ app()->getLocale() === 'ar' ? 'شبكة TCP/IP' : 'Network TCP/IP' }}</option>
                                        <option value="usb">USB</option>
                                        <option value="windows">{{ app()->getLocale() === 'ar' ? 'ويندوز (Spooler)' : 'Windows Spooler' }}</option>
                                    </select>
                                </div>

                                {{-- Network fields --}}
                                <div class="col-md-8" id="pf_ip">
                                    <label class="form-label">IP Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="p_ip_address" placeholder="192.168.1.200">
                                </div>
                                <div class="col-md-4" id="pf_port">
                                    <label class="form-label">Port</label>
                                    <input type="number" class="form-control" id="p_port" value="9100">
                                </div>

                                {{-- USB field --}}
                                <div class="col-md-12 d-none" id="pf_usb">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'مسار الجهاز' : 'Device Path' }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="p_usb_device" placeholder="/dev/usb/lp0">
                                </div>

                                {{-- Windows field --}}
                                <div class="col-md-12 d-none" id="pf_windows">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'اسم الطابعة في ويندوز' : 'Windows Printer Name' }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="p_windows_printer_name" placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: POS-80' : 'e.g. POS-80' }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'عرض الورق' : 'Paper Width' }}</label>
                                    <select class="form-select" id="p_paper_width">
                                        <option value="80">80mm (48 {{ app()->getLocale() === 'ar' ? 'حرف' : 'chars' }})</option>
                                        <option value="58">58mm (32 {{ app()->getLocale() === 'ar' ? 'حرف' : 'chars' }})</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'مجموعة الأحرف' : 'Character Set' }}</label>
                                    <select class="form-select" id="p_character_set">
                                        <option value="CP720">CP720 ({{ app()->getLocale() === 'ar' ? 'عربي' : 'Arabic' }})</option>
                                        <option value="CP437">CP437 (English)</option>
                                        <option value="UTF-8">UTF-8</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'عدد النسخ' : 'Copies' }}</label>
                                    <input type="number" class="form-control" id="p_copies" value="1" min="1" max="10">
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" id="p_auto_cut" checked>
                                        <label class="form-check-label" for="p_auto_cut">
                                            {{ app()->getLocale() === 'ar' ? 'قطع تلقائي للورق' : 'Auto Cut Paper' }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" id="p_auto_open_drawer">
                                        <label class="form-check-label" for="p_auto_open_drawer">
                                            {{ app()->getLocale() === 'ar' ? 'فتح درج النقود تلقائياً' : 'Auto Open Cash Drawer' }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="p_is_default">
                                        <label class="form-check-label" for="p_is_default">
                                            {{ app()->getLocale() === 'ar' ? 'طابعة افتراضية' : 'Set as Default Printer' }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="p_is_active" checked>
                                        <label class="form-check-label" for="p_is_active">
                                            {{ app()->getLocale() === 'ar' ? 'مفعّلة' : 'Active' }}
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ app()->getLocale() === 'ar' ? 'ملاحظات' : 'Notes' }}</label>
                                    <input type="text" class="form-control" id="p_notes" placeholder="{{ app()->getLocale() === 'ar' ? 'اختياري' : 'Optional' }}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                            <button type="button" class="btn btn-outline-info" data-fn="testPrinterFromModal">
                                <i class="fas fa-wifi me-1"></i>{{ app()->getLocale() === 'ar' ? 'اختبار الاتصال' : 'Test Connection' }}
                            </button>
                            <button type="button" class="btn btn-primary" data-fn="savePrinter">
                                <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Save Button --}}
            <div class="save-btn-wrapper">
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-secondary" data-fn="loadSettings">
                        <i class="fas fa-undo me-1"></i>{{ __('pos.cancel') }}
                    </button>
                    <button class="btn btn-primary btn-lg px-4" data-fn="saveSettings" id="saveBtn">
                        <i class="fas fa-save me-2"></i>{{ __('pos.save') }}
                        <span id="saveSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                    </button>
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script @nonce>
        let allSettings = {};
        let roles = [];
        let permissions = [];
        let users = [];
        let currentRole = null;

        // Load roles and permissions
        async function loadRolesAndPermissions() {
            await loadRoles();
            await loadPermissions();
            await loadUsers();
        }

        async function loadRoles() {
            const res = await apiCall('{{ route('roles.all') }}');
            roles = res.roles || [];
            renderRolesTable();

            // Update role select dropdown
            const roleSelect = document.getElementById('roleSelect');
            if (roleSelect) {
                roleSelect.innerHTML = '<option value="">{{ __('pos.select_role') }}</option>';
                roles.forEach(role => {
                    roleSelect.innerHTML += `<option value="${role.name}">${role.name}</option>`;
                });
            }
        }

        async function loadPermissions() {
            const res = await apiCall('{{ route('permissions.all') }}');
            permissions = res.permissions || [];
        }

        async function loadUsers() {
            const res = await apiCall('{{ route('users.all') }}');
            users = res.users || [];
            renderUsersTable();

            // keep the old assign-role select in sync
            const userSelect = document.getElementById('userSelect');
            if (userSelect) {
                userSelect.innerHTML = '<option value="">{{ __('pos.select_user') }}</option>';
                users.forEach(u => {
                    userSelect.innerHTML += `<option value="${u.id}">${u.full_name}</option>`;
                });
            }
        }

        const CURRENT_USER_ID = {{ auth()->id() }};
        @can('manage_roles')
        const CAN_IMPERSONATE = true;
        @else
        const CAN_IMPERSONATE = false;
        @endcan

        function renderUsersTable() {
            const tbody = document.getElementById('usersTableBody');
            if (!tbody) return;
            if (users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">{{ __('pos.no_users') }}</td></tr>`;
                return;
            }
            tbody.innerHTML = users.map(u => {
                const impersonateBtn = (CAN_IMPERSONATE && u.id !== CURRENT_USER_ID)
                    ? `<button class="btn btn-sm btn-outline-info me-1" data-fn="impersonateUser" data-args='["${u.id}"]' title="{{ __('pos.login_as') }}">
                           <i class="fas fa-user-secret"></i>
                       </button>`
                    : '';
                return `
                <tr>
                    <td><strong>${escHtml(u.full_name)}</strong></td>
                    <td><code>${escHtml(u.username)}</code></td>
                    <td><span class="badge bg-secondary">${escHtml(u.role || '')}</span></td>
                    <td>
                        <span class="badge ${u.is_active ? 'bg-success' : 'bg-danger'}">
                            ${u.is_active ? '{{ __('pos.active') }}' : '{{ __('pos.inactive') }}'}
                        </span>
                    </td>
                    <td>
                        ${impersonateBtn}
                        <button class="btn btn-sm btn-outline-primary me-1" data-fn="showUserModal" data-args='["${u.id}"]' title="{{ __('pos.edit') }}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-${u.is_active ? 'warning' : 'success'} me-1"
                                data-fn="toggleUser" data-args='["${u.id}"]'
                                title="${u.is_active ? '{{ __('pos.deactivate') }}' : '{{ __('pos.activate') }}'}">
                            <i class="fas fa-${u.is_active ? 'ban' : 'check'}"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" data-fn="deleteUser" data-args='["${u.id}"]' title="{{ __('pos.delete') }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        async function impersonateUser(userId) {
            if (userId instanceof Element) return;
            const user = users.find(u => u.id == userId);
            if (!user) return;
            const label = '{{ app()->getLocale() === "ar" ? "تسجيل الدخول بوصفك" : "Login as" }}';
            if (!confirm(`${label} "${user.full_name}"?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('impersonate') }}/${userId}`;
            form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
            document.body.appendChild(form);
            form.submit();
        }

        function escHtml(str) {
            return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function showUserModal(userId) {
            // dispatcher appends the element when no data-args is set
            if (userId instanceof Element) userId = null;
            const isEdit = !!userId;
            const user   = isEdit ? users.find(u => u.id == userId) : null;

            document.getElementById('editUserId').value       = isEdit ? userId : '';
            document.getElementById('userModalTitle').textContent = isEdit
                ? '{{ __('pos.edit_user') }}'
                : '{{ __('pos.add_user') }}';
            document.getElementById('userFullName').value     = user?.full_name ?? '';
            document.getElementById('userUsername').value     = user?.username ?? '';
            document.getElementById('userUsername').readOnly  = isEdit;
            document.getElementById('userPassword').value     = '';
            document.getElementById('userPasswordLabel').innerHTML = isEdit
                ? '{{ __('pos.password') }} <small class="text-muted">({{ __('pos.leave_blank_to_keep') }})</small>'
                : '{{ __('pos.password') }} <span class="text-danger">*</span>';
            document.getElementById('userIsActive').checked   = user ? user.is_active : true;

            // populate role select
            const sel = document.getElementById('userRole');
            sel.innerHTML = '<option value="">{{ __('pos.select_role') }}</option>';
            roles.forEach(r => {
                sel.innerHTML += `<option value="${r.name}" ${user?.role === r.name ? 'selected' : ''}>${r.name}</option>`;
            });

            bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show();
        }

        async function saveUser() {
            const userId   = document.getElementById('editUserId').value;
            const isEdit   = !!userId;
            const payload  = {
                full_name: document.getElementById('userFullName').value.trim(),
                role:      document.getElementById('userRole').value,
                is_active: document.getElementById('userIsActive').checked,
            };
            const pwd = document.getElementById('userPassword').value;

            if (!isEdit) {
                payload.username = document.getElementById('userUsername').value.trim();
                payload.password = pwd;
            } else if (pwd) {
                payload.password         = pwd;
                payload.password_confirm = pwd;
            }

            const url    = isEdit ? `{{ url('api/users') }}/${userId}` : '{{ route('users.store') }}';
            const method = isEdit ? 'PUT' : 'POST';
            const res    = await apiCall(url, method, payload);

            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('userModal'))?.hide();
                showToast(isEdit ? '{{ __('pos.user_updated') }}' : '{{ __('pos.user_created') }}');
                await loadUsers();
            } else {
                const msg = res.errors ? Object.values(res.errors).flat().join(' | ') : (res.message || '{{ __('pos.error') }}');
                showToast(msg, 'danger');
            }
        }

        async function deleteUser(userId) {
            const user = users.find(u => u.id == userId);
            if (!confirm(`{{ __('pos.confirm_delete') }} "${user?.full_name}"?`)) return;
            const res = await apiCall(`{{ url('api/users') }}/${userId}`, 'DELETE');
            if (res.success) {
                showToast('{{ __('pos.user_deleted') }}');
                await loadUsers();
            } else {
                showToast(res.message || '{{ __('pos.error') }}', 'danger');
            }
        }

        async function toggleUser(userId) {
            const res = await apiCall(`{{ url('api/users') }}/${userId}/toggle-active`, 'POST');
            if (res.success) {
                showToast(res.is_active ? '{{ __('pos.user_activated') }}' : '{{ __('pos.user_deactivated') }}');
                await loadUsers();
            } else {
                showToast(res.message || '{{ __('pos.error') }}', 'danger');
            }
        }

        function renderRolesTable() {
            const tbody = document.getElementById('rolesTableBody');
            if (!tbody) return;

            if (roles.length === 0) {
                tbody.innerHTML =
                    `<tr><td colspan="4" class="text-center text-muted py-3">{{ __('pos.no_roles') }}</td></tr>`;
                return;
            }

            tbody.innerHTML = roles.map(role => `
        <tr>
            <td><strong>${role.name}</strong></td>
            <td><span class="badge bg-secondary">${role.guard_name}</span></td>
            <td><span class="badge bg-info">${role.permissions?.length || 0}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-warning" data-fn="editRole" data-args='["${role.id}"]'>
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" data-fn="deleteRole" data-args='["${role.id}"]'>
                    <i class="fas fa-trash"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary" data-fn="selectRole" data-args='["${role.id}"]'>
                    <i class="fas fa-key"></i>
                </button>
            </td>
        </tr>
    `).join('');
        }

        function selectRole(roleId) {
            currentRole = roles.find(r => r.id == roleId);
            document.getElementById('selectedRoleName').innerHTML = currentRole.name;
            document.getElementById('savePermBtn').style.display = 'block';
            renderPermissionsGrid();
        }

        function renderPermissionsGrid() {
            const container = document.getElementById('permissionsGrid');
            if (!currentRole) {
                container.innerHTML = '<div class="col-12 text-center text-muted py-3">{{ __('pos.select_role') }}</div>';
                return;
            }

            const rolePermNames = currentRole.permissions?.map(p => p.name) || [];

            // Group permissions by category
            const grouped = {};
            permissions.forEach(perm => {
                const category = perm.name.split('_')[0];
                if (!grouped[category]) grouped[category] = [];
                grouped[category].push(perm);
            });

            let html = '';
            for (const [category, perms] of Object.entries(grouped)) {
                html +=
                    `<div class="col-12 mb-3"><strong class="text-capitalize">${category}</strong><hr class="my-1"></div>`;
                perms.forEach(perm => {
                    const isChecked = rolePermNames.includes(perm.name);
                    html += `
                <div class="col-md-4 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${perm.name}" id="perm_${perm.id}"
                            ${isChecked ? 'checked' : ''} data-on-change="togglePermission">
                        <label class="form-check-label" for="perm_${perm.id}">
                            ${perm.name.replace(/_/g, ' ')}
                        </label>
                    </div>
                </div>
            `;
                });
            }
            container.innerHTML = html;
        }

        let changedPermissions = [];

        function togglePermission(el) {
            const permName = el.value;
            const checked  = el.checked;
            if (checked) {
                if (!changedPermissions.includes(permName)) changedPermissions.push(permName);
            } else {
                changedPermissions = changedPermissions.filter(p => p !== permName);
            }
        }

        // Replace the savePermissions function in your blade file
async function savePermissions() {
    if (!currentRole) return;

    // Get ALL checked permissions, not just changed ones
    const allCheckedPermissions = [];
    document.querySelectorAll('#permissionsGrid input[type="checkbox"]:checked').forEach(checkbox => {
        allCheckedPermissions.push(checkbox.value);
    });

    const res = await apiCall(`{{ url('api/roles') }}/${currentRole.id}/permissions`, 'POST', {
        permissions: allCheckedPermissions  // Send all permissions, not just changes
    });

    if (res.success) {
        showToast(res.message);
        await loadRoles();  // Reload to get updated permissions
        if (currentRole) {
            currentRole = roles.find(r => r.id == currentRole.id);
            renderPermissionsGrid();
        }
    }
}

        function showCreateRoleModal() {
            document.getElementById('roleModalTitle').innerHTML = '{{ __('pos.create_role') }}';
            document.getElementById('editRoleId').value = '';
            document.getElementById('roleName').value = '';
            document.getElementById('guardName').value = 'web';
            new bootstrap.Modal(document.getElementById('roleModal')).show();
        }

        function editRole(roleId) {
            const role = roles.find(r => r.id == roleId);
            document.getElementById('roleModalTitle').innerHTML = '{{ __('pos.edit_role') }}';
            document.getElementById('editRoleId').value = role.id;
            document.getElementById('roleName').value = role.name;
            document.getElementById('guardName').value = role.guard_name;
            new bootstrap.Modal(document.getElementById('roleModal')).show();
        }

        async function saveRole() {
            const roleId = document.getElementById('editRoleId').value;
            const data = {
                name: document.getElementById('roleName').value,
                guard_name: document.getElementById('guardName').value
            };

            let url = '{{ route('roles.store') }}';
            let method = 'POST';
            if (roleId) {
                url = `{{ url('api/roles') }}/${roleId}`;
                method = 'PUT';
            }

            const res = await apiCall(url, method, data);
            if (res.success) {
                showToast(res.message);
                bootstrap.Modal.getInstance(document.getElementById('roleModal')).hide();
                await loadRoles();
            }
        }

        async function deleteRole(roleId) {
            if (!confirm('{{ __('pos.confirm_delete') }}')) return;

            const res = await apiCall(`{{ url('api/roles') }}/${roleId}`, 'DELETE');
            if (res.success) {
                showToast(res.message);
                await loadRoles();
                if (currentRole?.id == roleId) {
                    currentRole = null;
                    document.getElementById('selectedRoleName').innerHTML = 'Select a role';
                    document.getElementById('savePermBtn').style.display = 'none';
                    document.getElementById('permissionsGrid').innerHTML =
                        '<div class="col-12 text-center text-muted py-3">{{ __('pos.select_role') }}</div>';
                }
            }
        }

        async function assignRoleToUser() {
            const userId = document.getElementById('userSelect').value;
            const roleName = document.getElementById('roleSelect').value;

            if (!userId || !roleName) {
                showToast('Please select both user and role', 'danger');
                return;
            }

            const res = await apiCall(`{{ url('api/users') }}/${userId}/roles`, 'POST', {
                role: roleName
            });
            if (res.success) {
                showToast(res.message);
                await loadUserRoles(userId);
            }
        }

        async function loadUserRoles(userId) {
            if (!userId) return;

            const res = await apiCall(`{{ url('api/users') }}/${userId}/roles`);
            const container = document.getElementById('userRolesInfo');
            if (res.roles && res.roles.length > 0) {
                container.innerHTML = `
            <div class="alert alert-info mb-0">
                <strong>{{ __('pos.current_roles') }}:</strong><br>
                ${res.roles.map(r => `<span class="badge bg-primary me-1">${r}</span>`).join('')}
            </div>
        `;
            } else {
                container.innerHTML = '<p class="text-muted text-center mb-0">{{ __('pos.no_roles_assigned') }}</p>';
            }
        }

        // Add user select change event
        document.addEventListener('DOMContentLoaded', () => {
            const userSelect = document.getElementById('userSelect');
            if (userSelect) {
                userSelect.addEventListener('change', (e) => loadUserRoles(e.target.value));
            }
        });

        // Add to showTab function
        const originalShowTab = window.showTab;
        window.showTab = function(tab, btn) {
            if (originalShowTab) originalShowTab(tab, btn);
            if (tab === 'roles')    loadRolesAndPermissions();
            if (tab === 'printing') { loadPrinters(); loadQueueStats(); }
        };

        async function loadSettings() {
            const res = await apiCall('{{ route('settings.all') }}');
            allSettings = res.settings || {};

            // Flatten all settings into a key->value map
            const flat = {};
            Object.values(allSettings).forEach(group => {
                Object.entries(group).forEach(([key, cfg]) => {
                    flat[key] = cfg.value;
                });
            });

            // Apply to inputs
            document.querySelectorAll('[data-key]').forEach(el => {
                const key = el.dataset.key;
                const val = flat[key];
                if (val === undefined) return;

                if (el.type === 'checkbox') {
                    el.checked = val === '1' || val === 'true' || val === true;
                } else {
                    el.value = val;
                }
            });

            toggleTaxFields();
            updateTaxPreview();
        }

        function toggleTaxFields() {
            const enabled = document.getElementById('s_tax_enabled').checked;
            document.getElementById('taxFields').style.opacity = enabled ? '1' : '0.4';
            document.getElementById('taxFields').style.pointerEvents = enabled ? 'auto' : 'none';
            document.getElementById('taxPreview').style.display = enabled ? 'block' : 'none';
            updateTaxPreview();
        }

        function updateTaxPreview() {
            const enabled = document.getElementById('s_tax_enabled')?.checked;
            const rate = parseFloat(document.getElementById('s_tax_rate')?.value) || 0;
            const inclusive = document.getElementById('s_tax_inclusive')?.checked;
            const price = parseFloat(document.getElementById('previewPrice')?.value) || 100;

            let beforeTax, taxAmount, total;

            if (!enabled || rate === 0) {
                beforeTax = price;
                taxAmount = 0;
                total = price;
            } else if (inclusive) {
                total = price;
                taxAmount = price - (price / (1 + rate / 100));
                beforeTax = price - taxAmount;
            } else {
                beforeTax = price;
                taxAmount = price * (rate / 100);
                total = price + taxAmount;
            }

            const fmt = n => n.toFixed(2);
            document.getElementById('previewBefore').textContent = fmt(beforeTax);
            document.getElementById('previewTax').textContent = `+${fmt(taxAmount)} (${rate}%)`;
            document.getElementById('previewTotal').textContent = fmt(total);
        }

        async function saveSettings() {
            const btn = document.getElementById('saveBtn');
            const spinner = document.getElementById('saveSpinner');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const settings = [];
            document.querySelectorAll('[data-key]').forEach(el => {
                const key = el.dataset.key;
                let value;
                if (el.type === 'checkbox') {
                    value = el.checked ? '1' : '0';
                } else {
                    value = el.value;
                }
                settings.push({
                    key,
                    value
                });
            });

            try {
                const res = await apiCall('{{ route('settings.update') }}', 'POST', {
                    settings
                });
                if (res.success) {
                    showToast(res.message || '{{ __('pos.settings_saved') }}');
                } else {
                    showToast(res.message || '{{ __('pos.error') }}', 'danger');
                }
            } catch (e) {
                showToast('{{ __('pos.error') }}', 'danger');
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        }

        function showTab(tab, btn) {
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.add('d-none'));
            document.querySelectorAll('.settings-nav .nav-link').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.remove('d-none');
            btn.classList.add('active');
        }

        // ── Thermal Printing JS ────────────────────────────────────────────────
        let printers = [];

        async function loadPrinters() {
            const res = await apiCall('{{ route('printing.printers.index') }}?active_only=0');
            printers = res.printers || [];
            renderPrintersTable();
        }

        function renderPrintersTable() {
            const tbody = document.getElementById('printersTableBody');
            if (!tbody) return;
            if (!printers.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-3">
                    {{ app()->getLocale() === 'ar' ? 'لا توجد طابعات مضافة بعد' : 'No printers added yet' }}
                </td></tr>`;
                return;
            }
            const isAr = document.documentElement.lang === 'ar' || document.dir === 'rtl';
            tbody.innerHTML = printers.map(p => {
                const connBadge = {
                    network: '<span class="badge bg-primary">TCP/IP</span>',
                    usb:     '<span class="badge bg-secondary">USB</span>',
                    windows: '<span class="badge bg-info text-dark">Windows</span>',
                }[p.connection_type] ?? p.connection_type;

                const connDetail = p.connection_type === 'network'
                    ? `<small class="text-muted d-block">${escHtml(p.ip_address ?? '')}:${p.port}</small>` : '';

                const defaultBadge = p.is_default
                    ? `<span class="badge bg-warning text-dark ms-1">${isAr ? 'افتراضية' : 'Default'}</span>` : '';

                return `<tr>
                    <td>
                        <strong>${escHtml(p.name)}</strong>${defaultBadge}
                        ${p.notes ? `<small class="text-muted d-block">${escHtml(p.notes)}</small>` : ''}
                    </td>
                    <td>${connBadge}${connDetail}</td>
                    <td><span class="badge bg-light text-dark border">${p.paper_width}mm</span></td>
                    <td>${escHtml(p.branch?.name ?? (isAr ? 'كل الفروع' : 'All Branches'))}</td>
                    <td>
                        <span class="badge ${p.is_active ? 'bg-success' : 'bg-danger'}">
                            ${p.is_active ? (isAr ? 'مفعّلة' : 'Active') : (isAr ? 'معطّلة' : 'Inactive')}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info me-1" title="${isAr ? 'اختبار' : 'Test'}"
                            data-fn="testPrinter" data-args='[${p.id}]'>
                            <i class="fas fa-wifi"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning me-1" title="${isAr ? 'افتراضية' : 'Set Default'}"
                            data-fn="setPrinterDefault" data-args='[${p.id}]' ${p.is_default ? 'disabled' : ''}>
                            <i class="fas fa-star"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary me-1" title="${isAr ? 'تعديل' : 'Edit'}"
                            data-fn="showPrinterModal" data-args='[${p.id}]'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" title="${isAr ? 'حذف' : 'Delete'}"
                            data-fn="deletePrinter" data-args='[${p.id}]'>
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        async function loadQueueStats() {
            const res = await apiCall('{{ route('printing.queue.stats') }}');
            const s   = res.stats ?? {};
            document.getElementById('qs_pending').textContent        = s.pending ?? 0;
            document.getElementById('qs_failed').textContent         = s.failed ?? 0;
            document.getElementById('qs_processing').textContent     = s.processing ?? 0;
            document.getElementById('qs_completed_today').textContent = s.completed_today ?? 0;
            document.getElementById('printQueueStats').style.removeProperty('display');
        }

        function togglePrinterFields() {
            const type = document.getElementById('p_connection_type').value;
            document.getElementById('pf_ip').classList.toggle('d-none',      type !== 'network');
            document.getElementById('pf_port').classList.toggle('d-none',    type !== 'network');
            document.getElementById('pf_usb').classList.toggle('d-none',     type !== 'usb');
            document.getElementById('pf_windows').classList.toggle('d-none', type !== 'windows');
        }

        function showPrinterModal(printerId) {
            if (printerId instanceof Element) printerId = null;
            const p = printerId ? printers.find(x => x.id == printerId) : null;
            const isAr = document.documentElement.lang === 'ar';

            document.getElementById('printerModalTitle').textContent = p
                ? (isAr ? 'تعديل طابعة' : 'Edit Printer')
                : (isAr ? 'إضافة طابعة' : 'Add Printer');
            document.getElementById('editPrinterId').value          = p?.id ?? '';
            document.getElementById('p_name').value                 = p?.name ?? '';
            document.getElementById('p_connection_type').value      = p?.connection_type ?? 'network';
            document.getElementById('p_ip_address').value           = p?.ip_address ?? '';
            document.getElementById('p_port').value                 = p?.port ?? 9100;
            document.getElementById('p_usb_device').value           = p?.usb_device ?? '';
            document.getElementById('p_windows_printer_name').value = p?.windows_printer_name ?? '';
            document.getElementById('p_paper_width').value          = p?.paper_width ?? '80';
            document.getElementById('p_character_set').value        = p?.character_set ?? 'CP720';
            document.getElementById('p_copies').value               = p?.copies ?? 1;
            document.getElementById('p_auto_cut').checked           = p ? !!p.auto_cut : true;
            document.getElementById('p_auto_open_drawer').checked   = !!p?.auto_open_drawer;
            document.getElementById('p_is_default').checked         = !!p?.is_default;
            document.getElementById('p_is_active').checked          = p ? !!p.is_active : true;
            document.getElementById('p_notes').value                = p?.notes ?? '';

            togglePrinterFields();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('printerModal')).show();
        }

        async function savePrinter() {
            const id = document.getElementById('editPrinterId').value;
            const type = document.getElementById('p_connection_type').value;
            const payload = {
                name:                 document.getElementById('p_name').value.trim(),
                connection_type:      type,
                ip_address:           type === 'network' ? document.getElementById('p_ip_address').value.trim() : null,
                port:                 type === 'network' ? parseInt(document.getElementById('p_port').value) : null,
                usb_device:           type === 'usb'     ? document.getElementById('p_usb_device').value.trim() : null,
                windows_printer_name: type === 'windows' ? document.getElementById('p_windows_printer_name').value.trim() : null,
                paper_width:          document.getElementById('p_paper_width').value,
                character_set:        document.getElementById('p_character_set').value,
                copies:               parseInt(document.getElementById('p_copies').value),
                auto_cut:             document.getElementById('p_auto_cut').checked,
                auto_open_drawer:     document.getElementById('p_auto_open_drawer').checked,
                is_default:           document.getElementById('p_is_default').checked,
                is_active:            document.getElementById('p_is_active').checked,
                notes:                document.getElementById('p_notes').value.trim() || null,
            };

            const url    = id ? `{{ url('api/printing/printers') }}/${id}` : '{{ route('printing.printers.store') }}';
            const method = id ? 'PUT' : 'POST';
            const res    = await apiCall(url, method, payload);

            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('printerModal'))?.hide();
                showToast(id ? '{{ app()->getLocale() === 'ar' ? 'تم تحديث الطابعة' : 'Printer updated' }}' : '{{ app()->getLocale() === 'ar' ? 'تمت إضافة الطابعة' : 'Printer added' }}');
                await loadPrinters();
            } else {
                const msg = res.errors ? Object.values(res.errors).flat().join(' | ') : (res.message || '{{ __('pos.error') }}');
                showToast(msg, 'danger');
            }
        }

        async function testPrinter(printerId) {
            if (printerId instanceof Element) return;
            showToast('{{ app()->getLocale() === 'ar' ? 'جارٍ اختبار الاتصال...' : 'Testing connection...' }}', 'info');
            const res = await apiCall(`{{ url('api/printing/printers') }}/${printerId}/test`, 'POST');
            showToast(
                res.success
                    ? '{{ app()->getLocale() === 'ar' ? '✅ الطابعة متصلة وتعمل' : '✅ Printer is reachable' }}'
                    : (res.message || '{{ app()->getLocale() === 'ar' ? 'فشل الاتصال' : 'Connection failed' }}'),
                res.success ? 'success' : 'danger'
            );
        }

        async function testPrinterFromModal() {
            const id   = document.getElementById('editPrinterId').value;
            const type = document.getElementById('p_connection_type').value;

            if (!id) {
                // Not saved yet — just validate IP reachability hint
                const ip = document.getElementById('p_ip_address').value.trim();
                if (type === 'network' && !ip) {
                    showToast('{{ app()->getLocale() === 'ar' ? 'أدخل عنوان IP أولاً' : 'Enter IP address first' }}', 'warning');
                } else {
                    showToast('{{ app()->getLocale() === 'ar' ? 'احفظ الطابعة أولاً لتتمكن من اختبارها' : 'Save printer first to test connection' }}', 'warning');
                }
                return;
            }
            await testPrinter(id);
        }

        async function setPrinterDefault(printerId) {
            const res = await apiCall(`{{ url('api/printing/printers') }}/${printerId}/set-default`, 'POST');
            if (res.success) {
                showToast('{{ app()->getLocale() === 'ar' ? 'تم تعيينها طابعة افتراضية' : 'Default printer updated' }}');
                await loadPrinters();
            } else {
                showToast(res.message || '{{ __('pos.error') }}', 'danger');
            }
        }

        async function deletePrinter(printerId) {
            const p = printers.find(x => x.id == printerId);
            const isAr = document.documentElement.lang === 'ar';
            if (!confirm(`${isAr ? 'حذف الطابعة' : 'Delete printer'} "${p?.name}"?`)) return;
            const res = await apiCall(`{{ url('api/printing/printers') }}/${printerId}`, 'DELETE');
            if (res.success) {
                showToast(isAr ? 'تم حذف الطابعة' : 'Printer deleted');
                await loadPrinters();
            } else {
                showToast(res.message || '{{ __('pos.error') }}', 'danger');
            }
        }

        // Load on init
        loadSettings();
        document.getElementById('s_tax_rate').addEventListener('input', updateTaxPreview);
        document.getElementById('s_tax_inclusive').addEventListener('change', updateTaxPreview);
        document.getElementById('previewPrice').addEventListener('input', updateTaxPreview);
    </script>
@endpush
