## Company Billing Validation (Checkout)

This document describes the **company-related checkout fields**, their **HTML / POST keys**, **stored meta keys**, and the **validation rules** that currently apply.

All logic is implemented in:

- `snippets/flatsome-func.php`  
  - Address fields & company fields: `custom_woocommerce_address_fields`  
  - Company visibility toggle (JS): `conditionally_hide_show_new_field`  
  - Checkout validation: `validate_custom_address_fields`  
  - My account address validation: `action_woocommerce_customer_save_address`

---

## 1. Company toggle

- **Label**: `مؤسسة ؟`
- **Base field key** (default address fields): `billing_company_checkbox`
- **Checkout input / POST key**: `billing_billing_company_checkbox`
- **Stored user/order meta key**: `billing_billing_company_checkbox`
- **Behavior**:
  - When **unchecked**:
    - All company-specific fields are **hidden**.
    - Values for company-related fields are cleared.
    - `billing_first_name` label is set to **"الاسم الثلاثي"**.
  - When **checked**:
    - All company-specific fields are **shown**.
    - `billing_first_name` label is set to **"اسم المؤسسة"**.
    - Triggers extra validation (see sections below).

---

## 2. Always-applied validation

### 2.1 Street / حي (billing address 1)

- **Label** (address fields): `الحي`
- **Checkout input / POST key**: `billing_address_1`
- **Stored meta key**: `billing_address_1`
- **Validation (always, company or individual)**:
  - Trimmed length must be **at least 3 characters**.
  - If shorter than 3, WooCommerce error is added:
    - `<strong>الشارع</strong> يجب أن يحتوي على 3 أحرف على الأقل.`

> Note: Although the label text is `الحي`, the error message refers to it as **الشارع**.

---

## 3. Company-only fields and rules

The following fields are **validated only when** the company checkbox is checked:

- Condition: `isset( $_POST['billing_billing_company_checkbox'] ) && ! empty( $_POST['billing_billing_company_checkbox'] )`

### 3.1 Company VAT number

- **Label**: `الرقم الضريبي للمؤسسة`
- **Base field key**: `billing_company_vat`
- **Checkout input / POST key**: `billing_billing_company_vat`
- **Stored meta key**: `billing_billing_company_vat`
- **Validation rules**:
  - Required (must not be empty).
  - Must contain **digits only** (`ctype_digit`).
  - Must be **exactly 15 digits** long.
  - Must **not** be all zeros (`/^0+$/` is invalid).
  - Must **start with `3`** and **end with `3`**.
- **Error message** (any failure):
  - `يرجى إدخال رقم ضريبي صحيح`

#### 3.1.1 My Account edit-address validation

When saving the billing address in **My Account → Addresses**:

- If `billing_billing_company_checkbox` is set but `billing_billing_company_vat` is empty:
  - Error: `<strong>الرمز الضريبي للمؤسسة</strong> مطلوب.`

---

### 3.2 Short address

- **Label**: `العنوان المختصر`
- **Base field key**: `short_address`
- **Checkout input / POST key**: `billing_short_address`
- **Stored meta key**: `billing_short_address`
- **Validation** (company mode only):
  - Required (must not be empty).
  - If empty:
    - Error: `<strong>العنوان المختصر</strong> مطلوب عند اختيار المؤسسة.`

---

### 3.3 Building number

- **Label**: `رقم المبنى`
- **Base field key**: `building_number`
- **Checkout input / POST key**: `billing_building_number`
- **Stored meta key**: `billing_building_number`
- **Validation** (company mode only):
  - Required (must not be empty).
  - Must contain **digits only** (`ctype_digit`).
  - Must be **exactly 4 digits** long.
  - Must **not** be all zeros (`/^0+$/` is invalid).
- **Error messages**:
  - If empty:
    - `<strong>رقم المبنى</strong> مطلوب عند اختيار المؤسسة.`
  - If non‑numeric:
    - `<strong>رقم المبنى</strong> يجب أن يحتوي على أرقام فقط.`
  - If wrong length:
    - `<strong>رقم المبنى</strong> يجب أن يتكون من 4 أرقام.`
  - If all zeros:
    - `<strong>رقم المبنى</strong> لا يمكن أن يكون مكونًا من أصفار فقط.`

---

### 3.4 Secondary number (sub-number)

- **Label**: `الرقم  الفرعي`
- **Base field key**: `address_second`
- **Checkout input / POST key**: `billing_address_second`
- **Stored meta key**: `billing_address_second`
- **Validation** (company mode only):
  - Required (must not be empty).
  - Must contain **digits only** (`ctype_digit`).
  - Must be **exactly 4 digits** long.
  - Must **not** be all zeros.
- **Error messages**:
  - If empty:
    - `<strong>الرقم الفرعي</strong> مطلوب عند اختيار المؤسسة.`
  - If non‑numeric:
    - `<strong>الرقم الفرعي</strong> يجب أن يحتوي على أرقام فقط.`
  - If wrong length:
    - `<strong>الرقم الفرعي</strong> يجب أن يتكون من 4 أرقام.`
  - If all zeros:
    - `<strong>الرقم الفرعي</strong> لا يمكن أن يكون مكونًا من أصفار فقط.`

---

### 3.5 District

- **Label**: `الحي`
- **Base field key**: `district`
- **Checkout input / POST key**: `billing_district`
- **Stored meta key**: `billing_district`
- **Validation** (company mode only):
  - Required (must not be empty).
  - If empty:
    - Error: `<strong>الحي</strong> مطلوب عند اختيار المؤسسة.`

---

### 3.6 Postal code

- **Label**: `الرمز  البريدي`
- **Base field key**: `postal_code`
- **Checkout input / POST key**: `billing_postal_code`
- **Stored meta key**: `billing_postal_code`
- **Validation** (company mode only):
  - Required (must not be empty).
  - Must contain **digits only** (`ctype_digit`).
  - Must be **exactly 5 digits** long.
  - Must **not** be all zeros.
- **Error messages**:
  - If empty:
    - `<strong>الرمز البريدي</strong> مطلوب عند اختيار المؤسسة.`
  - If non‑numeric:
    - `<strong>الرمز البريدي</strong> يجب أن يحتوي على أرقام فقط.`
  - If wrong length:
    - `<strong>الرمز البريدي</strong> يجب أن يتكون من 5 أرقام.`
  - If all zeros:
    - `<strong>الرمز البريدي</strong> لا يمكن أن يكون مكونًا من أصفار فقط.`

---

## 4. Front-end visibility logic

The visibility and reset behavior are handled by `conditionally_hide_show_new_field()` via `wc_enqueue_js`:

- JS binds to `input#billing_billing_company_checkbox`.
- When **unchecked**:
  - Hides:
    - `#billing_billing_company_vat_field`
    - `#billing_short_address_field`
    - `#billing_building_number_field`
    - `#billing_district_field`
    - `#billing_address_second_field`
    - `#billing_postal_code`
  - Clears values of all the above inputs (plus `billing_address_1`).
  - Sets `label[for="billing_first_name"]` to **"الاسم الثلاثي"**.
- When **checked**:
  - Shows all of the above fields.
  - Sets `label[for="billing_first_name"]` to **"اسم المؤسسة"**.

---

## 5. Summary

- **Individual customers**:
  - Only `billing_address_1` minimum length rule applies.
  - Company‑specific fields are hidden and not required.
- **Company customers** (checkbox checked):
  - **All** company fields become required and are strictly validated:
    - VAT structure and length.
    - Numeric-only building, sub-number, postal-code with exact lengths and non-zero constraints.
    - Non-empty short address and district.

Use this document whenever you need to:

- Integrate with external systems (e.g. ERP, ZATCA) and map field names.
- Debug checkout validation errors.
- Safely change labels or behavior without breaking current rules.


