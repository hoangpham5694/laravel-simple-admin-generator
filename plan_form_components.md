# Kế hoạch: Blade Components cho form input

## 1. Mục tiêu

Xây dựng một bộ **anonymous Blade Components** cho các trường form của
`simple-admin-generator`. Các component phải dùng được trong Laravel base sau
khi cài package, đồng thời thay thế input HTML lặp lại trong UI do
`sag:generate_ui` tạo ra.

API công khai dự kiến:

```blade
<x-sag-form.input
    name="email"
    label="Email"
    type="email"
    :value="$user->email ?? null"
    required
    placeholder="name@example.com"
/>

<x-sag-form.select name="role_id" label="Vai trò" :options="$roles" />

<x-sag-form.multi-select
    name="role_ids"
    label="Các vai trò"
    :options="$roles"
    :selected="$user->roles->pluck('id')"
/>
<x-sag-form.textarea name="bio" label="Giới thiệu" :value="$user->bio ?? null" />
```

Mục tiêu của phase đầu là Blade server-rendered tương thích Laravel 9+,
Bootstrap/AdminLTE sẵn có. Không thêm Livewire, Alpine hoặc dependency Composer
mới. Riêng datetime picker dùng Flatpickr như asset JavaScript opt-in được
package cung cấp cục bộ; Laravel base không cần Vite, npm hay CDN khi runtime.

## 2. Phạm vi phase đầu

### Component được cung cấp

| Component | HTML sinh ra | Khả năng chính |
| --- | --- | --- |
| `x-sag-form.field` | `div.form-group` | wrapper cho label, help text và error; là component dùng nội bộ hoặc khi cần custom control |
| `x-sag-form.input` | `input` | text, email, password, number, date, time, URL, tel và các native input type khác |
| `x-sag-form.textarea` | `textarea` | old value, rows, error state |
| `x-sag-form.select` | `select` single | một selected value, placeholder và options mảng/Collection |
| `x-sag-form.multi-select` | `select multiple` | nhiều selected value, name mảng và options mảng/Collection |
| `x-sag-form.checkbox` | `input[type=checkbox]` | checked state, value và label |
| `x-sag-form.radio` | `input[type=radio]` | checked state và label |
| `x-sag-form.file` | `input[type=file]` | validation/error và forward các thuộc tính `accept`, `multiple` |
| `x-sag-form.datetime` | `input[type=text]` + Flatpickr | chọn datetime thống nhất, format submit cố định và lazy-load asset |

`password`, `number`, `date` và `time` không cần component riêng: gọi qua
`<x-sag-form.input type="password">`, v.v. Điều này giữ API nhỏ và tránh lặp
template.

### Ngoài phạm vi

- Rich text editor, select2, tag input và upload AJAX.
- Model binding tự động hoặc form builder PHP fluent API.
- Tự suy luận field từ migration/database schema.
- Chuyển toàn bộ form hiện hữu của package trong cùng thay đổi đầu tiên.

Các tính năng ngoài phạm vi có thể được bổ sung bằng component riêng ở phase
sau, không làm thay đổi API cốt lõi.

## 3. Vị trí file và cơ chế phân phối

### Source of truth trong package

Tạo các view dưới đây:

```text
src/resources/views/components/sag-form/
├── field.blade.php
├── input.blade.php
├── textarea.blade.php
├── select.blade.php
├── multi-select.blade.php
├── checkbox.blade.php
├── radio.blade.php
└── file.blade.php
```

Trong `PackageServiceProvider`, đăng ký anonymous component namespace cho view
package, ví dụ namespace `sag-form`. Nhờ đó component hoạt động ngay khi
package được cài và không bắt buộc publish:

```blade
<x-sag-form.input name="title" label="Tiêu đề" />
```

### Publish để tùy biến

Thêm publish group `sag-form-components`, sao chép từ thư mục source trên sang:

```text
resources/views/components/sag-form/
```

Lệnh người dùng chạy khi muốn override giao diện:

```bash
php artisan vendor:publish --tag=sag-form-components
```

Quy ước override cần được kiểm tra trong implementation. Nếu Laravel ưu tiên
anonymous component của app trước namespace package thì giữ cùng tag
`sag-form`; nếu không, provider cần đăng ký đường dẫn component publish theo
thứ tự ưu tiên phù hợp. Tiêu chí nghiệm thu là sau khi publish và sửa một file
ở app, `<x-sag-form.input>` phải render bản đã chỉnh sửa.

Không gộp component vào `sag:install`: command hiện tại copy layout và asset;
component package đã dùng được ngay. Publish là thao tác rõ ràng, không vô tình
ghi đè thư mục `resources/views/components` của ứng dụng.

## 4. Hợp đồng chung cho component

### Props chung

Các component control nhận tối thiểu:

| Prop | Ý nghĩa |
| --- | --- |
| `name` (bắt buộc) | Tên request field, ví dụ `email`, `profile.phone`, `roles[]` |
| `label` | Nhãn hiển thị; không render label nếu là `null` |
| `id` | Tùy chọn; nếu không có thì tự sinh từ `name` |
| `value` / `selected` / `checked` | Giá trị ban đầu theo loại control; `multi-select.selected` nhận array/Collection |
| `required`, `disabled`, `readonly` | Boolean HTML attributes phù hợp với loại field |
| `help` | Help text dưới field |

Mọi HTML attribute chưa được khai báo phải được forward qua `$attributes`, bao
gồm `placeholder`, `autocomplete`, `maxlength`, `min`, `max`, `accept`,
`data-*`, `aria-*` và custom class.

### Quy tắc value và validation

1. `input`, `textarea`, `select`: ưu tiên `old($name, $value)`.
2. Checkbox/radio: `old($name, $checked)` quyết định trạng thái checked.
3. `name` có dot notation dùng error key nguyên gốc (`profile.email`), còn id
   chuyển `.`/`[`/`]` thành `_` để hợp lệ HTML.
4. Nếu `$errors->has($name)`, control thêm `is-invalid` và render
   `<div class="invalid-feedback">` với message đầu tiên.
5. Class mặc định là `form-control` (text/select/textarea/file) hoặc
   `form-check-input` (checkbox/radio); class truyền vào phải được merge, không
   bị thay thế.
6. Giá trị phải luôn được Blade escape. Không render raw HTML từ `label`,
   `help`, option label hoặc value.

### Component `field`

`field` chịu trách nhiệm cho phần dùng chung: wrapper, label, help và error.
Các input component gọi `field` để không lặp markup validation. Component phải
có default slot dành cho native control:

```blade
<x-sag-form.field name="code" label="Mã" help="Chỉ dùng chữ và số.">
    <input name="code" class="form-control">
</x-sag-form.field>
```

Việc dùng `field` làm primitive cũng cho phép thêm control tùy biến ở ứng dụng
mà vẫn có cùng UI validation.

## 5. Thiết kế riêng từng component

### Input và textarea

- `input`: default `type="text"`; hỗ trợ tất cả native type, trừ `file` nên
  dùng component `file` để tránh gán value không hợp lệ.
- Không truyền `value` cho password nếu không có old input; không prefill file
  input trong mọi trường hợp.
- `textarea`: nội dung đặt giữa opening/closing tag, không phải `value`
  attribute; mặc định `rows="3"`.

### Number input

`number` sử dụng component chung, không có template riêng:

```blade
<x-sag-form.input
    name="stock"
    label="Tồn kho"
    type="number"
    :value="$product->stock"
    min="0"
    step="1"
/>
```

- `min`, `max`, `step`, `inputmode` và các native number attributes được
  forward qua `$attributes`; component không tự áp đặt min/max/step vì tùy
  nghiệp vụ.
- Giá trị phải lấy trực tiếp bằng `old($name, $value)`; không dùng truthy check
  như `$value ?: ''`, vì `0` là giá trị hợp lệ và phải được render thành
  `value="0"`.
- Không ép kiểu float/int trong component. Browser request number dưới dạng
  string và các giá trị thập phân có thể cần độ chính xác nghiệp vụ; việc cast
  thuộc về Form Request/model. Component chỉ escape giá trị để render HTML.
- `type="number"` chỉ hỗ trợ trải nghiệm client; validation server vẫn bắt
  buộc, ví dụ `stock => ['required', 'integer', 'min:0']` hoặc
  `price => ['required', 'numeric', 'min:0']`.
- Không dùng `number` cho các mã có số 0 đầu (`postal_code`, `phone`, mã sản
  phẩm); các trường đó phải dùng `type="text"` và rule validation phù hợp.

### Datetime input với Flatpickr

Tạo component riêng `x-sag-form.datetime`, render `input[type=text]` và khởi
tạo Flatpickr. Native `datetime-local` vẫn có thể dùng qua `input` khi app muốn
hoàn toàn không dùng JavaScript, nhưng không phải API datetime mặc định.

```blade
<x-sag-form.datetime
    name="published_at"
    label="Thời điểm xuất bản"
    :value="$post->published_at?->format('Y-m-d H:i')"
    minute-increment="5"
/>
```

- Giá trị submit và giá trị từ `old($name, $value)` có format cố định
  `Y-m-d H:i` (ví dụ `2026-08-26 14:30`). Flatpickr hiển thị `d/m/Y H:i`;
  caller phải format Carbon/model trước khi truyền vào.
- `old('published_at', $value)` vẫn được ưu tiên. Giá trị old từ request đã có
  đúng format cho browser nếu validation redirect quay lại cùng request.
- Props gồm `minute-increment` (default `5`), `min-date`, `max-date`,
  `enable-seconds` và `locale` (default `vi`); component chuyển chúng thành
  data attributes cho script khởi tạo. Bật seconds thì format submit là
  `Y-m-d H:i:S`.
- Flatpickr không giải quyết timezone. Việc diễn giải timezone, chuyển sang
  UTC và validate/cast `published_at` thuộc Form Request, controller/model;
  component không được tự đổi timezone.
- Nếu JavaScript bị tắt, input text vẫn submit format server và Laravel
  validation vẫn là nguồn quyết định cuối cùng.

### Flatpickr: tải và phân phối asset

Chốt dùng Flatpickr `4.6.13`, pin exact version. AdminLTE 3 liệt kê Tempus
Dominus là plugin form, nhưng nhánh Bootstrap 4 của thư viện đó không còn được
hỗ trợ; AdminLTE 4 hiện dùng Flatpickr trong advanced-form demo.

1. Thêm `"flatpickr": "4.6.13"` vào `package.json`, chạy `npm install` để
   cập nhật lockfile, rồi tạo script `vendor:flatpickr` copy các distributable
   file từ `node_modules/flatpickr/dist`.
2. Commit các file đã copy vào source asset chính thức của package:

   ```text
   src/resources/assets/plugins/flatpickr/
   ├── flatpickr.min.css
   ├── flatpickr.min.js
   └── l10n/vi.js
   ```

   Runtime không tham chiếu `node_modules`, không dùng CDN; project Laravel
   cài package không cần npm/Vite để dùng component.
3. `src/resources/assets` là nguồn chính thức của Flatpickr. Cập nhật
   `sag:install`: sau khi copy legacy `stubs/public`, copy/overlay
   `src/resources/assets` vào `public/sag`. Vì vậy sau `php artisan
   sag:install`, thư viện luôn có tại `public/sag/plugins/flatpickr/`.
4. Giữ publish mapping `assets` hiện có. Với Laravel base không chạy installer,
   tài liệu phải nêu `php artisan vendor:publish --tag=assets --force`; lệnh
   này cũng copy chính source asset vào `public/sag`. Đây là hai luồng asset
   được hỗ trợ, không được yêu cầu copy tay file Flatpickr vào ứng dụng.
5. `x-sag-form.datetime` dùng `@once` cùng `@push('style')` để nạp CSS, và
   `@once` cùng `@push('script')` để nạp core JS, locale `vi` và file khởi tạo
   `sag/dist/js/sag-form.js`. Layout stub hiện có sẵn hai stack nên không cần
   app base chỉnh layout. Nhiều datetime field chỉ tải asset một lần.
6. Tạo `src/resources/assets/dist/js/sag-form.js`: query mọi element
   `[data-sag-flatpickr="datetime"]`, parse data attributes và khởi tạo
   Flatpickr. Script phải bỏ qua element đã có instance để an toàn khi view
   được render lại.

### Single select: `select`

Props: `options`, `selected`, `placeholder`.

- Luôn render single select (`<select name="role_id">`), không nhận và không
  render thuộc tính `multiple`.
- `selected` là một scalar; ưu tiên `old($name, $selected)` khi render option
  được chọn.
- `placeholder` được render thành option rỗng đầu tiên khi được truyền.
- Chấp nhận array hoặc `Illuminate\Support\Collection`; option đơn giản có
  dạng `value => label`.

Ví dụ:

```blade
<x-sag-form.select
    name="status"
    label="Trạng thái"
    :options="['draft' => 'Bản nháp', 'published' => 'Đã xuất bản']"
    :selected="$post->status"
    placeholder="-- Chọn trạng thái --"
/>
```

### Multi-select: `multi-select`

Props: `options`, `selected`; không có prop `multiple` vì component luôn render
`<select multiple>`.

- Component tự chuẩn hóa name thành `category_ids[]` nếu caller truyền
  `category_ids`. Nếu caller đã truyền `category_ids[]`, không được thêm `[]`
  lần nữa.
- `selected` chấp nhận array hoặc Collection. Giá trị từ
  `old('category_ids', $selected)` được chuẩn hóa về mảng rồi so sánh
  string-safe với từng option (`1` và `'1'` được xem là cùng giá trị).
- Không render placeholder/option rỗng vì nó có thể trở thành một giá trị được
  submit; dùng `help` hoặc một option do caller chủ động đưa vào `options`.
- Chấp nhận array hoặc `Illuminate\Support\Collection` với mapping `value =>
  label`.

```blade
<x-sag-form.multi-select
    name="category_ids"
    label="Danh mục"
    :options="$categories->pluck('name', 'id')"
    :selected="$post->categories->pluck('id')"
    size="6"
/>
```

Request từ ví dụ multi-select sẽ có key `category_ids` với giá trị mảng; rule
validation tương ứng là `category_ids => ['array']` và
`category_ids.* => ['integer', 'exists:categories,id']`.

Phase đầu không hỗ trợ optgroup/object mapping đặc biệt; ghi rõ điều này ở
README để tránh API mơ hồ.

### Checkbox và radio

- Render theo Bootstrap/AdminLTE `.form-check` để label có thể click.
- Props: `value` (mặc định `1`), `checked` (mặc định `false`), `inline`.
- Khi request cũ có giá trị, so sánh dạng string an toàn (`(string)`) để tránh
  sai khác giữa integer ID và request string.
- Checkbox boolean phải truyền hidden input chỉ khi có prop rõ ràng
  `unchecked-value`; mặc định không sinh hidden input nhằm tránh thay đổi
  semantics của form hiện tại.

### File

- Không nhận `value`.
- Forward `accept`, `multiple`, `capture` và các thuộc tính HTML khác.
- README nhắc form cha cần `enctype="multipart/form-data"`.

## 6. Các thay đổi code theo thứ tự

1. Thêm Flatpickr exact version vào `package.json`, đưa CSS/JS/locale đã build
   vào `src/resources/assets`, thêm `sag-form.js`, rồi sửa `sag:install` để
   overlay source assets lên `public/sag`.
2. Tạo các template component (gồm `datetime`) và thống nhất helper Blade/PHP
   nhỏ dùng chung trong template (`id`, error state, old value).
3. Cập nhật `PackageServiceProvider` để load/register anonymous component và
   thêm publish tag `sag-form-components` trong `runningInConsole()`.
4. Cập nhật `stubs/Generator/views/ui/form.blade.php`: thay ba input HTML mẫu
   bằng ba lời gọi `<x-sag-form.input>`. Giữ placeholder column hiện tại để
   không làm thay đổi phạm vi generator.
5. Bổ sung một ví dụ form có `select`, checkbox, file và datetime trong tài
   liệu, không
   tự ý thêm field vào UI generated.
6. Cập nhật `README.md` với API, quy tắc old/error, hai lệnh asset hỗ trợ
   (`sag:install` hoặc publish `assets`) và ví dụ custom component đã publish.
7. Chỉ sau khi test ổn, cân nhắc chuyển các form package hiện hữu
   (`src/resources/views/admin/form.blade.php`, menu form) theo từng PR nhỏ.

## 7. Kiểm thử và tiêu chí nghiệm thu

Thêm feature test mới (ví dụ `tests/Feature/FormComponentsTest.php`) qua
Orchestra Testbench. Tạo route/view test nhỏ để render component; không cần
trình duyệt hay JavaScript.

| Nhóm test | Ca kiểm tra |
| --- | --- |
| Render cơ bản | input có label, `for`/`id`, name, type, class mặc định |
| Attributes | placeholder, `data-*`, aria và custom class vẫn xuất hiện |
| Old input | old value ghi đè giá trị từ prop cho input/textarea/select |
| Number | render đúng `value="0"`, old value `"0"`, và forward `min`/`max`/`step` mà không ép kiểu |
| Datetime | component push asset một lần, ưu tiên old value và submit đúng format `Y-m-d H:i` |
| Flatpickr assets | sau `sag:install` và publish `assets`, có CSS, JS và locale `vi` ở `public/sag/plugins/flatpickr/` |
| Validation | error thêm `is-invalid` và message đúng field, gồm dot notation |
| Select | `select` single và `multi-select` riêng; old value, placeholder, selected và `name[]` không bị lặp |
| Checkable | checkbox/radio checked bởi old value hoặc prop |
| File | không có value attribute và forward `accept`/`multiple` |
| Generator stub | file `form.blade.php` generated chứa API component đúng namespace |
| Publish | mapping provider có tag `sag-form-components` và đường dẫn đích đúng |

Chạy tối thiểu:

```bash
composer test
```

Tiêu chí hoàn thành:

- Người dùng package render được mọi component phase đầu bằng `<x-sag-form.*>`.
- Validation Laravel và `old()` hoạt động đồng nhất cho các field phù hợp.
- UI theo style AdminLTE/Bootstrap hiện hữu; Flatpickr là asset cục bộ chỉ tải cho datetime component.
- `sag:generate_ui Blog` tạo form mẫu đã sử dụng component.
- README đủ để một Laravel base dùng và tùy biến component.

## 8. Rủi ro và quyết định cần giữ

- **Xung đột component namespace:** dùng prefix riêng `sag-form`, không dùng
  `<x-input>` hoặc `<x-form.input>` chung chung vì dễ va chạm app/package khác.
- **Publish override:** phải có test/manual verification về thứ tự discovery;
  đây là điểm cần xác nhận trước khi công bố là API override.
- **JavaScript datetime opt-in:** chỉ `x-sag-form.datetime` tải Flatpickr;
  date/file/select native khác không kéo plugin. select2 và editor vẫn để
  phase sau.
- **Tương thích version:** dùng Blade directives/attribute bag có sẵn trên
  Laravel 9+; không dùng cú pháp chỉ có ở phiên bản mới hơn.

## 9. Hướng mở rộng sau phase đầu

1. `x-sag-form.input-group` với prefix/suffix/icon bằng named slot.
2. `x-sag-form.checkbox-group` và `radio-group` cho danh sách options.
3. Field schema renderer phục vụ generator CRUD, có mapping loại cột database
   sang các component này.
4. Integration opt-in cho select2/editor; datetime đã chuẩn hóa bằng Flatpickr.
5. Accessibility audit: aria-describedby nối help/error, required indicator và
   keyboard/focus behavior cho control nâng cao.
