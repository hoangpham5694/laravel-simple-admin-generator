# Kế hoạch: `sag:generate_crud`

## 1. Mục tiêu

Tạo command sinh CRUD hoàn chỉnh từ một Eloquent model hoặc database table.
Command sẽ introspect schema, xác định kiểu dữ liệu của các cột và sinh UI dùng
anonymous Blade components `x-sag-form.*`, controller, routes, menu và views
để xem, thêm, sửa, xóa record.

Mục tiêu phase đầu là Laravel 9+ và MySQL. Không thay đổi database schema.

## 2. Public API

```bash
php artisan sag:generate_crud --model=Employee
php artisan sag:generate_crud --table=employees
php artisan sag:generate_crud --table=employees --route-name=staff-members --controller=StaffMemberController --model-class=App\Models\StaffMember
```

- Command không nhận positional argument.
- `--model={model}` nhận trực tiếp tên/FQCN Eloquent model, ví dụ `Employee`
  hoặc `App\Models\Employee`.
- `--table={table}` nhận trực tiếp tên database table, ví dụ `employees`.
- Chỉ được dùng một trong hai option; command báo lỗi nếu thiếu option, giá trị
  option rỗng hoặc dùng đồng thời để không suy đoán sai source.
- `--force` cho phép ghi đè file generated đã tồn tại.
- `--skip-menu` không tạo menu item.

Command signature dự kiến:

```php
sag:generate_crud
    {--model=}
    {--table=}
    {--route-name=}
    {--controller=}
    {--model-class=}
    {--force}
    {--skip-menu}
```

### `--route-name={name}`

- Đặt resource identifier dùng đồng thời cho URI, Blade view folder và route
  names. Ví dụ `--route-name=staff-members` tạo URI
  `/{sag.prefix}/staff-members`, views `resources/views/sag/staff-members` và
  names `sag.staff-members.index`, `sag.staff-members.create`, v.v.
- Giá trị phải là kebab-case theo regex
  `^[a-z0-9]+(?:-[a-z0-9]+)*$`; không nhận `/`, `.`, khoảng trắng hoặc ký tự
  khác để tránh route/path traversal và route name không hợp lệ.
- Nếu không truyền: với `--model`, mặc định là kebab-case plural của basename
  model; với `--table`, mặc định là kebab-case của table name.
- Giá trị này cũng được dùng để tạo menu key bằng snake_case.

### `--controller={class}`

- Đặt basename controller sẽ được generate trong namespace
  `App\Http\Controllers\SAG`; ví dụ `--controller=StaffMemberController`
  tạo `app/Http/Controllers/SAG/StaffMemberController.php`.
- Giá trị phải là PHP StudlyCase class name và bắt buộc kết thúc bằng
  `Controller`; không nhận namespace hoặc path. Điều này giữ destination và
  generated route class nhất quán.
- Nếu không truyền, mặc định là `{SingularStudly(route-name)}Controller`, ví dụ
  `staff-members` thành `StaffMemberController`.
- Nếu target class đã tồn tại, command dừng trừ khi có `--force`.

### `--model-class={fqcn}`

- Chỉ dùng cùng `--table`, để chỉ định Eloquent model mà command sẽ generate
  hoặc sử dụng; ví dụ `--model-class=App\Models\StaffMember`.
- Giá trị bắt buộc là FQCN trong namespace `App\Models\`, có basename PHP
  StudlyCase và không chứa path traversal. File đích được suy ra từ PSR-4, ví
  dụ `app/Models/StaffMember.php`.
- Nếu model chưa tồn tại, command tạo model với `$table`, `$fillable` và
  `$casts` suy ra từ schema. Nếu model đã tồn tại, command xác minh đó là
  Eloquent model và `$model->getTable()` khớp table đã nhập trước khi dùng.
- Không được dùng với `--model`, vì `--model` đã là source class chính xác;
  command báo lỗi khi hai option cùng xuất hiện.
- Nếu không truyền cùng `--table`, mặc định là
  `App\Models\{SingularStudly(table)}`.

## 3. Tái sử dụng `sag:generate_ui`

Tách phần tạo UI hiện có thành service nội bộ, ví dụ `CrudScaffolder`:

1. Tạo folder `resources/views/sag/{resource}`.
2. Tạo controller trong `App\Http\Controllers\SAG`.
3. Đăng ký routes.
4. Tạo menu item nếu bảng `admin_menu_items` tồn tại.
5. Thay placeholder trong stubs.

`sag:generate_ui` giữ hành vi hiện tại và dùng service này với stubs UI đơn
giản. `sag:generate_crud` dùng cùng service nhưng truyền metadata schema để
sinh CRUD đầy đủ. Không duy trì hai luồng copy route/view/menu độc lập.

## 4. Xác định source và schema

### Source model

- Resolve class model và kiểm tra class kế thừa Eloquent `Model`.
- Lấy `$model->getTable()`, primary key, `$fillable`, `$guarded` và `$casts`
  khi có.

### Source table

- Kiểm tra table tồn tại.
- Suy ra model class mặc định `App\Models\{SingularStudlyTable}`.
- Tôn trọng `--model-class` khi option được truyền và dùng class đó cho
  controller/routes/views đã generate.
- Tạo model nếu chưa có, với `$table`, `$fillable` từ các cột writable và
  `$casts` cho boolean, numeric, json, date/datetime.
- Không ghi đè model có sẵn nếu không có `--force`.

### Metadata cột

Đọc tên cột, type, native type, nullable, default, length, primary key và
auto-increment bằng Schema Builder. Với MySQL, đọc metadata native bổ sung để
nhận diện `enum`/`set`; các driver không cung cấp đủ thông tin sẽ fallback và
in cảnh báo trong console.

Loại khỏi form mặc định:

- Primary key auto-increment.
- `created_at`, `updated_at`, `deleted_at`.
- Generated/computed columns.
- Binary/blob columns; command ghi cảnh báo thay vì sinh control không phù hợp.

## 5. Mapping schema sang SAG component

| Loại cột | Component sinh ra | Validation |
| --- | --- | --- |
| `varchar`, `char` | `x-sag-form.input type="text"` | `string`, `max:length` |
| `email`, `*_email` | `x-sag-form.input type="email"` | `email` |
| `text`, `mediumtext`, `longtext` | `x-sag-form.textarea` | `string` |
| Integer types | `x-sag-form.input type="number"` | `integer` |
| `decimal`, `float`, `double` | `input type="number" step="any"` | `numeric` |
| `boolean`, `tinyint(1)` | `x-sag-form.checkbox unchecked-value="0"` | `boolean` |
| `date` | `x-sag-form.input type="date"` | `date` |
| `time` | `x-sag-form.input type="time"` | `date_format:H:i` |
| `datetime`, `timestamp` | `x-sag-form.datetime` | `date_format:Y-m-d H:i` |
| `json` | `x-sag-form.textarea` | `json` |
| `enum` | `x-sag-form.select` | `in:...` |
| `set` | `x-sag-form.multi-select` | array + `in:...` |

- Cột nullable nhận rule `nullable`; các cột còn lại nhận `required`.
- Cột `*_id` mặc định là number input. Không tự suy luận relation, options hay
  display label vì điều đó có thể tạo UI/query sai.
- JSON được encode/decode ở controller hoặc model cast, không render raw HTML.
- Tên cột và label luôn Blade escape.

## 6. Controller và thao tác CRUD

Sinh resource controller có đầy đủ:

- `index`: search trên text/string column phù hợp, pagination, order theo
  primary key.
- `create` và `store`: form, validation, tạo record.
- `edit` và `update`: route-model binding, validation, update chỉ các cột
  writable đã introspect.
- `destroy`: xóa record, tôn trọng soft delete khi model sử dụng trait.
- Metadata title/breadcrumb phù hợp `sag.layouts.app`.

Không mass-assign field ngoài whitelist sinh từ schema. Unique index validation,
foreign-key select và authorization policy không nằm trong phase đầu.

## 7. Routes, views và menu

Sinh cấu trúc:

```text
resources/views/sag/{resource}/
├── index.blade.php
├── create.blade.php
├── edit.blade.php
└── form.blade.php
```

- `form.blade.php` dùng `x-sag-form.*` theo mapping.
- `index` có search, pagination, Create button và actions Edit/Delete.
- `create/edit` dùng POST/PUT, CSRF và hiển thị validation error qua component.
- Đăng ký REST routes với names `sag.{resource}.*`:
  `index`, `create`, `store`, `edit`, `update`, `destroy`.
- Trước khi thêm route, kiểm tra marker/resource để tránh duplicate routes.
- Tạo `AdminMenuItem` trỏ đến `sag.{resource}.index`, trừ khi dùng
  `--skip-menu`.

## 8. Stubs và an toàn file system

- Thêm stubs riêng cho CRUD controller, model và các CRUD view.
- File generated có marker `Generated by sag:generate_crud` để xác định nguồn.
- Command kiểm tra trước toàn bộ target file/route; nếu đã tồn tại thì liệt kê
  conflict và dừng, không ghi một phần.
- Chỉ ghi đè với `--force`.
- Console in rõ model/table, resource name, danh sách cột sử dụng, mapping,
  cột bị bỏ qua và cảnh báo fallback type.

## 9. Kiểm thử MySQL

Thêm feature tests và chạy theo hướng dẫn MySQL trong `AGENTS.md`:

1. Resolve đúng source `--table` và `--model`.
2. Mapping string, text, numeric, boolean, date, time, datetime, json,
   enum/set và nullable.
3. Không đưa primary key, timestamps và blob vào form.
4. Controller/view/routes sinh đúng namespace, resource và components.
5. CRUD create, update và delete record trên table test.
6. Validation failure và old input render chính xác.
7. Không ghi đè khi không có `--force`.
8. Chạy command hai lần không tạo route/menu trùng.

## 10. Thứ tự triển khai

1. Tạo DTO/service `CrudSourceResolver` và `ColumnMetadataResolver`.
2. Tách phần chung hiện có của `GenerateUiCommand` sang `CrudScaffolder`.
3. Tạo command `GenerateCrudCommand`, đăng ký trong provider.
4. Tạo stubs controller/model/views và schema-to-component mapper.
5. Sinh routes/menu idempotent và có kiểm tra conflict.
6. Viết feature tests MySQL, chạy toàn bộ suite Docker.
7. Cập nhật README với API, mapping, giới hạn và ví dụ sử dụng.

## 11. Ngoài phạm vi phase đầu

- Tự suy luận quan hệ hoặc select label cho foreign key.
- Upload file, rich text, select2, AJAX.
- Composite primary key.
- Tạo/sửa migration hay database schema.
- Policy/permission tự động.
