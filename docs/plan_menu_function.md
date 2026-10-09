# Kế hoạch chức năng quản lý menu admin

## Mục tiêu

Xây dựng chức năng quản lý menu cho admin panel. Dữ liệu menu được lưu trong một bảng
admin_menu_items; các item cấp gốc có parent_id = null, còn item con liên kết với item cha
qua parent_id.

Menu item có thể trỏ đến Laravel route, đường dẫn từ root của base Laravel, đường dẫn tương đối
với admin prefix của package, URL trực tiếp bên ngoài, hoặc đóng vai trò menu nhóm không có link.

## Thiết kế bảng admin_menu_items

```php
Schema::create('admin_menu_items', function (Blueprint $table) {
    $table->id();

    $table->foreignId('parent_id')
        ->nullable()
        ->constrained('admin_menu_items')
        ->nullOnDelete();

    // Key định danh duy nhất của item, ví dụ: administrators.index.
    $table->string('key')->unique();

    $table->string('title');
    $table->string('icon')->nullable(); // Ví dụ: fas fa-users
    $table->unsignedInteger('sort_order')->default(0);

    // Giá trị backing của AdminMenuLinkType.
    $table->string('link_type', 20)->default('none');

    // route: sag.admin.index
    // root_path: /products
    // admin_path: admins
    // url: https://example.com
    $table->string('target')->nullable();

    // Tham số route, ví dụ: {"id": 1}
    $table->json('parameters')->nullable();

    // Dành cho tích hợp phân quyền trong tương lai.
    $table->string('permission')->nullable();

    $table->string('target_window', 10)->default('_self'); // _self | _blank
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['parent_id', 'sort_order']);
});
```

## Enum cho loại link

Định nghĩa native backed enum tại src/Enums/AdminMenuLinkType.php:

```php
namespace HoangPhamDev\SimpleAdminGenerator\Enums;

enum AdminMenuLinkType: string
{
    case None = 'none';
    case Route = 'route';
    case RootPath = 'root_path';
    case AdminPath = 'admin_path';
    case Url = 'url';
}
```

Database vẫn lưu backing value dạng string. Model sẽ cast link_type về enum để code luôn gọi bằng
AdminMenuLinkType, ví dụ:

```php
protected $casts = [
    'link_type' => AdminMenuLinkType::class,
    'parameters' => 'array',
    'is_active' => 'boolean',
];

if ($menuItem->link_type === AdminMenuLinkType::Route) {
    // Resolve Laravel route.
}
```

MenuUrlResolver nhận AdminMenuLinkType và dùng match theo các case của enum; không so sánh magic
string như 'route' trong controller hoặc Blade. Form lấy option từ AdminMenuLinkType::cases(),
validation chỉ chấp nhận các backing value của enum. Seeder dùng trực tiếp
AdminMenuLinkType::Route thay vì string.

Native enum yêu cầu PHP 8.1+. Khi triển khai cần cập nhật PHP requirement hiện tại của package
từ PHP 7.0.0 lên PHP 8.1. Nếu vẫn phải hỗ trợ PHP 7, dùng class enum-like có constants thay cho
native enum; không thể dùng cú pháp enum của PHP 8.1.

## Thư viện giao diện menu

Sử dụng jsTree cho màn hình quản lý menu vì package hiện tại đã dùng jQuery/AdminLTE. jsTree hỗ trợ
tree từ JSON, icon Font Awesome, kéo-thả, tạo/sửa/xóa node và context menu.

Các điểm tích hợp:

1. API trả dữ liệu theo định dạng jsTree: id, parent (dùng # cho root), text, icon và data chứa
   link_type, target, parameters, key và các metadata cần cho form chỉnh sửa.
2. Bật core.check_callback và plugin dnd; dùng callback để chặn việc kéo item vào chính nó hoặc
   vào descendant của nó.
3. Sau sự kiện move_node hoặc reorder, gửi một payload cấu trúc cây tới endpoint sắp xếp. Backend
   cập nhật parent_id và sort_order trong một transaction, không tin dữ liệu thứ tự từ client
   nếu item không thuộc tập hợp menu hợp lệ.
4. Context menu hoặc các nút bên cạnh node mở form tạo/sửa/xóa hiện có; không dùng inline-edit
   của jsTree làm nguồn lưu dữ liệu cuối cùng.
5. Sau khi backend lưu thành công, reload hoặc refresh node jsTree từ API để phản ánh key được
   chuẩn hóa và thứ tự thật trong database.

## Quy ước tạo URL

| link_type | Giá trị target | Cách tạo URL |
| --- | --- | --- |
| none | null | Không tạo thẻ link; dùng làm menu nhóm. |
| route | sag.admin.index | route($target, $parameters ?? []) |
| root_path | /products | url($target) |
| admin_path | admins | Ghép với config('sag.prefix'), ví dụ /admin/admins. |
| url | https://example.com | Dùng trực tiếp sau khi kiểm tra URL hợp lệ. |

Không lưu admin prefix vào target của admin_path; điều này giúp menu tự cập nhật khi base project
thay đổi config(sag.prefix).

## Các bước triển khai

1. Tạo migration và model AdminMenuItem trong package.
2. Khai báo quan hệ parent() và children(); chỉ lấy các item is_active và sắp xếp theo sort_order.
3. Tạo enum AdminMenuLinkType và khai báo cast link_type trong model. Tạo helper hoặc service
   MenuUrlResolver để chuyển enum, target và parameters thành URL.
   Không đặt logic này trực tiếp trong Blade.
4. Tạo controller, endpoint JSON cho jsTree và request cho CRUD: danh sách menu dạng cây,
   tạo/sửa/xóa item, chọn item cha, key, thứ tự, icon, loại link, URL/route và target window.
5. Chuẩn hóa key trước khi lưu:
   - Cột key trong database luôn bắt buộc và unique.
   - Form có thể để trống key; khi đó sinh local key bằng slug của title, ví dụ "User Roles"
     thành "user-roles".
   - Với item gốc, key cuối cùng là local key.
   - Với item con, key cuối cùng là parent_key.local_key, ví dụ administrators.index.
   - Nếu người dùng nhập key cho item con, chỉ nhận local key; bỏ prefix parent_key nếu họ nhập
     trùng để tránh tạo administrators.administrators.
   - Khi local key hoặc parent của một item thay đổi, cập nhật key của toàn bộ descendant trong
     một database transaction. Có thể từ chối thao tác nếu key mới trùng item khác.
   - Không dùng title đã slug để ghi đè local key do người dùng chủ động nhập.
6. Validation theo loại link:
   - none: target phải rỗng.
   - link_type: chỉ nhận backing value của AdminMenuLinkType.
   - route: route phải tồn tại; parameters phải là JSON/object hợp lệ.
   - root_path: phải bắt đầu bằng / và không phải external URL.
   - admin_path: là path tương đối, không bắt đầu bằng /, không chứa scheme/host.
   - url: URL hợp lệ với scheme http hoặc https.
7. Ngăn lỗi cấu trúc cây: không cho item làm cha của chính nó hoặc descendant của nó. Khi xóa item
   cha, parent_id của item con sẽ thành null.
8. Render sidebar từ item cấp gốc và đệ quy các item con; hiển thị trạng thái active dựa trên
   URL/route hiện tại.
9. Cài đặt và khởi tạo jsTree cho màn hình quản lý; lưu kết quả kéo-thả qua endpoint reorder.
10. Tích hợp permission khi package có authorization; trước mắt item có permission rỗng luôn hiển thị.
11. Tạo seeder riêng cho menu hệ thống theo nội dung bên dưới.
12. Viết feature tests cho CRUD, validation, chuẩn hóa key, sắp xếp cây và tất cả loại link.

## Seeder menu hệ thống mặc định

Tạo class Database/Seeders/AdminMenuItemSeeder.php. Seeder chỉ quản lý các item có key cố định,
dùng firstOrCreate theo key để có thể chạy lặp lại mà không sinh menu trùng và không xóa các menu
do người dùng tạo.

Dữ liệu mặc định đề xuất:

| key | parent | title | link_type | target | sort_order | icon |
| --- | --- | --- | --- | --- | --- | --- |
| dashboard | null | Dashboard | AdminMenuLinkType::Route | sag.dashboard | 10 | fas fa-tachometer-alt |
| profile | null | Profile | AdminMenuLinkType::Route | sag.profile | 20 | fas fa-user |
| administrators | null | Administrators | AdminMenuLinkType::None | null | 30 | fas fa-users |
| administrators.index | administrators | Index | AdminMenuLinkType::Route | sag.admin.index | 10 | far fa-circle |
| administrators.create | administrators | Create | AdminMenuLinkType::Route | sag.admin.create | 20 | far fa-circle |
| system | null | System | AdminMenuLinkType::None | null | 40 | fas fa-cogs |
| system.menus | system | Menus | AdminMenuLinkType::Route | sag.menu.index | 10 | fas fa-sitemap |

Trình tự trong seeder:

1. Tạo Dashboard, Profile, Administrators và System trước để lấy ID của các menu nhóm.
2. Tạo Index và Create với parent_id là ID của Administrators; tạo Menus với parent_id là ID của System.
3. Không cập nhật parent_id, title, icon, thứ tự hoặc link của item đã được quản trị viên chỉnh sửa.
   Chỉ insert khi key chưa tồn tại; parent_id được gán khi tạo mới.
4. Gọi seeder này từ command riêng sag:seed-menu. Command sag:install sẽ gọi command này sau migrate.
5. Viết test chạy seeder hai lần và xác nhận chỉ có một bản ghi cho mỗi key hệ thống.

## Tiêu chí nghiệm thu

- Có thể tạo menu cấp gốc và nhiều cấp con.
- Sidebar chỉ render item đang active và theo đúng thứ tự.
- Mỗi loại link tạo đúng URL.
- Cập nhật admin prefix trong config không cần sửa các item admin_path.
- Seeder tạo đầy đủ menu hệ thống một lần duy nhất khi được chạy nhiều lần.
- Key luôn tồn tại, unique và item con dùng đúng prefix key của parent.
- Không thể tạo vòng lặp menu hoặc lưu link không hợp lệ.
