# Kế hoạch: Navbar/sidebar search và `sag:generate_search_provider`

## 1. Mục tiêu

Cho phép ứng dụng Laravel sử dụng ô search trên navbar của SAG để tìm kiếm
trên nhiều nguồn dữ liệu do ứng dụng đăng ký. Ô search ở sidebar cũng submit
đến cùng màn hình kết quả; chỉ navbar có gợi ý AJAX khi nhập.

Package cung cấp giao diện, endpoint, contract và cơ chế tổng hợp kết quả.
Ứng dụng Laravel chịu trách nhiệm query model, lọc quyền/tenant và tạo URL
đến bản ghi. Package cung cấp console command để generate search provider
theo cùng một chuẩn.

Tài liệu này là kế hoạch triển khai; các API bên dưới chưa được hiện thực.

## 2. Hiện trạng

- `src/resources/views/layouts/navbar.blade.php` có `navbar-search-block`,
  nhưng form chưa có `action` và input chưa có `name`.
- `stubs/views/layouts/navbar.blade.php` cũng có markup search cần cập nhật.
- Sidebar source/stub hiện dùng `data-widget="sidebar-search"` của AdminLTE
  với input không có `name`, chưa có form submit đến search của package.
- Package chưa có endpoint tìm kiếm chung.
- `sag:generate_crud` tạo search riêng trong trang danh sách từng resource;
  giữ hành vi này độc lập với navbar search.
- Package đã hỗ trợ publish cấu hình qua `sag-config` và layouts qua
  `sag-layouts`. Ứng dụng đã publish navbar có thể đang dùng view override.
- Routes sử dụng `sag.prefix` và `sag.middleware`; các trang quản trị được
  bảo vệ bằng middleware `admin`, xác thực qua guard `admin`.

## 3. Phạm vi bản đầu

- Submit GET từ navbar đến trang kết quả riêng.
- Sidebar search submit GET đến cùng trang kết quả, không dùng AJAX.
- AJAX search khi nhập trên navbar, hiển thị danh sách gợi ý ngay dưới input.
- Kết quả hiển thị trong một danh sách chung; mỗi item có nhãn provider nguồn.
  Giới hạn số bản ghi trả về từ từng provider.
- Đăng ký provider tường minh trong `config/sag.php`.
- Console command generate provider có skeleton và chế độ sinh query mẫu.
- Không tự quét model, bảng hoặc thư mục để đăng ký nguồn dữ liệu.
- Tìm kiếm full-text và phân trang danh sách để phase sau.

## 4. Kiến trúc và public API

```text
Navbar
  → Nhập từ khóa → GET /{sag.prefix}/search/suggestions?q=...
    → SearchManager → Gợi ý AJAX dưới input → Click mở trang detail
  → Submit form / Enter
  → GET /{sag.prefix}/search?q=...
  → SearchController
  → SearchManager
  → Các SearchProvider đã đăng ký
  → Danh sách kết quả chung, mỗi item có nhãn provider
  → Trang bản ghi trong ứng dụng

Sidebar → Submit GET /{sag.prefix}/search?q=...
        → Cùng SearchController, SearchManager và màn hình kết quả
```

### Cấu hình

Thêm vào `config/config.php` của package; ứng dụng tùy chỉnh trong
`config/sag.php`:

```php
'search' => [
    'enabled' => false,
    'providers' => [],
    'min_length' => 2,
    'max_length' => 200,
    'limit_per_provider' => 10,
    'suggestions' => [
        'enabled' => true,
        'debounce_ms' => 300,
        'limit_per_provider' => 3,
        'max_results' => 10,
    ],
],
```

Ví dụ ứng dụng bật search:

```php
'search' => [
    'enabled' => true,
    'providers' => [
        App\Admin\Search\ProductSearchProvider::class,
        App\Admin\Search\OrderSearchProvider::class,
    ],
    'min_length' => 2,
    'max_length' => 200,
    'limit_per_provider' => 10,
    'suggestions' => [
        'enabled' => true,
        'debounce_ms' => 300,
        'limit_per_provider' => 3,
        'max_results' => 10,
    ],
],
```

### Điều kiện hiển thị navbar/sidebar search

- Nếu config chưa có cấu hình `search`, hoặc không có key `search.enabled`,
  mặc định tính năng tắt và ẩn toàn bộ khung search ở navbar lẫn sidebar.
- Nếu `search.enabled = false`, ẩn toàn bộ khung search ở cả hai vị trí.
- Chỉ hiển thị khi `search.enabled = true` và có provider đăng ký. Danh sách
  provider thiếu hoặc rỗng cũng khiến cả hai khung bị ẩn.
- Navbar ẩn cả icon mở search, form và vùng gợi ý; sidebar ẩn cả form tìm
  kiếm. Không chỉ ẩn input hoặc vô hiệu hóa nút submit.
- Điều kiện được kiểm tra ở server trước khi render Blade, không dùng CSS
  hoặc JavaScript để che khung search đã render. Khi khung bị ẩn, không khởi
  tạo AJAX search hoặc gửi request gợi ý.
- Dùng mặc định an toàn khi đọc config, ví dụ
  `config('sag.search.enabled', false)` và
  `config('sag.search.providers', [])`; không truy cập key chưa tồn tại.
- Cấu hình mặc định package giữ `enabled => false`. Ứng dụng có config cũ
  chưa khai báo search tiếp tục ẩn search sau khi merge config; chỉ bật khi
  ứng dụng chủ động cấu hình `enabled => true` và đăng ký provider.

Endpoint trả 404 khi bị tắt hoặc chưa có provider. Giữ route được khai báo
ổn định; kiểm tra trạng thái ở runtime để tương thích route cache.
`search.suggestions.enabled` chỉ điều khiển gợi ý AJAX; khi tắt, form vẫn
submit đến màn hình search. `search.enabled` điều khiển cả hai luồng.

### Contract `SearchProvider`

Namespace dự kiến: `HoangPhamDev\SimpleAdminGenerator\Contracts`.

```php
interface SearchProvider
{
    /** Nhãn nguồn dữ liệu hiển thị trên từng kết quả search. */
    public function label(): string;

    /** @return iterable<SearchResult> */
    public function search(
        string $keyword,
        \Illuminate\Contracts\Auth\Authenticatable $admin,
        int $limit,
    ): iterable;
}
```

`SearchResult` là DTO trong namespace `Data`, gồm:

| Field | Kiểu | Ý nghĩa |
| --- | --- | --- |
| `title` | `string` | Tiêu đề bản ghi |
| `description` | `?string` | Mô tả ngắn, tùy chọn |
| `url` | `string` | URL đến trang bản ghi |

`label()` là nhãn provider hiển thị trên từng kết quả, ví dụ “Sản phẩm”.
Provider nhận admin hiện tại để
thực hiện kiểm tra quyền và giới hạn dữ liệu trước khi trả kết quả.

### Nhãn hiển thị bắt buộc của provider

Mỗi provider phải triển khai `label(): string` để khai báo nhãn hiển thị ở
màn hình search. Đây là tên dễ hiểu cho người dùng, không phải tên class PHP.
Ví dụ:

```php
// ProductSearchProvider
public function label(): string
{
    return 'Sản phẩm';
}

// OrderSearchProvider
public function label(): string
{
    return 'Đơn hàng';
}
```

- Nhãn phải là chuỗi không rỗng sau khi trim; manager kiểm tra và báo lỗi cấu
  hình nếu provider trả nhãn rỗng, không fallback sang tên class.
- Provider có thể trả chuỗi dịch như `__('Sản phẩm')` để hỗ trợ đa ngôn ngữ.
- Manager lấy nhãn một lần cho mỗi provider trong một lượt tìm kiếm và gắn
  vào `providerLabel` của từng item do provider đó trả về.
- Màn hình search hiển thị nhãn dưới dạng badge hoặc text cạnh tiêu đề của
  từng kết quả, dùng Blade escaped output. Nhãn không tạo nhóm kết quả.
- Nhãn chỉ phục vụ hiển thị; dùng `providerClass` để xác định provider nguồn,
  không dùng nhãn làm định danh vì các nhãn có thể trùng hoặc được dịch.
- Stub generate phải có sẵn phương thức `label()` với TODO sửa nhãn cho phù
  hợp nghiệp vụ. Nhãn mẫu lấy từ tên provider, ví dụ `Product` thành
  `Product`; ứng dụng chỉnh thành “Sản phẩm” trước khi sử dụng.

### Khai báo route detail trong từng provider

Mỗi provider khai báo named route của loại dữ liệu và ánh xạ tham số từ bản
ghi. Khi tạo `SearchResult`, provider resolve URL bằng Laravel `route()`.
Trang search dùng trực tiếp `SearchResult::url` làm link; không cần biết model
hay route của từng loại dữ liệu.

Ví dụ các route do ứng dụng định nghĩa:

```php
Route::get('/products/{product}', [ProductController::class, 'show'])
    ->name('sag.product.detail');

Route::get('/orders/{order}', [OrderController::class, 'show'])
    ->name('sag.order.detail');
```

Các route detail phải được ứng dụng đặt trong group prefix, authentication
và authorization phù hợp. Package không tự tạo route detail từ provider.

Đề xuất thêm `AbstractEloquentSearchProvider` trong namespace `Search`,
triển khai `SearchProvider` và cung cấp helper cho các provider dùng Eloquent:

```php
abstract class AbstractEloquentSearchProvider implements SearchProvider
{
    abstract protected function routeName(): string;

    /** @return array<string, mixed> */
    abstract protected function routeParameters(
        \Illuminate\Database\Eloquent\Model $record,
    ): array;

    protected function resultUrl(
        \Illuminate\Database\Eloquent\Model $record,
    ): string {
        return route($this->routeName(), $this->routeParameters($record));
    }
}
```

Provider sản phẩm khai báo route và tên tham số chính xác:

```php
class ProductSearchProvider extends AbstractEloquentSearchProvider
{
    public function label(): string
    {
        return 'Sản phẩm';
    }

    protected function routeName(): string
    {
        return 'sag.product.detail';
    }

    protected function routeParameters(Model $record): array
    {
        return ['product' => $record];
    }

    // search() do ứng dụng triển khai.
    // Bên trong search(), map mỗi bản ghi đã được lọc quyền thành:
    // new SearchResult(
    //     title: (string) $record->name,
    //     description: (string) $record->sku,
    //     url: $this->resultUrl($record),
    // );
}
```

Các đoạn code minh họa dùng imports tương ứng cho `Model`, `SearchProvider`,
`SearchResult` và `AbstractEloquentSearchProvider` khi triển khai thực tế.
Provider đơn hàng dùng `sag.order.detail` và `['order' => $record]`.

Quy tắc ánh xạ tham số:

- Tên key phải khớp placeholder của route: `{product}` dùng `product`,
  `{id}` dùng `id`. Không suy đoán từ tên model tại runtime.
- Khi route dùng route key mặc định của model, truyền model để Laravel lấy
  `getRouteKey()`, bao gồm model override `getRouteKeyName()` sang `slug`.
- Khi route dùng binding riêng như `{product:slug}` nhưng route key mặc định
  vẫn là ID, ánh xạ tường minh `['product' => $record->slug]`.
- Route nhiều tham số được xử lý trong `routeParameters()`, ví dụ
  `['tenant' => $record->tenant, 'product' => $record]`. Query phải lọc tenant
  và quyền trước đó; khai báo route không thay thế kiểm tra quyền.
- Route không tồn tại hoặc thiếu tham số là lỗi cấu hình; không fallback về
  `#` hoặc URL đoán được. Thực hiện kiểm tra khi generate và khi resolve URL.

Trong view kết quả:

```blade
<a href="{{ $result->url }}">{{ $result->title }}</a>
```

Click mở trang detail trong cùng tab. Không cần endpoint redirect trung gian.
Provider lấy dữ liệu từ API hoặc nguồn khác vẫn có thể triển khai trực tiếp
`SearchProvider` và tự cung cấp `url`, không bắt buộc dùng abstract class.

### `SearchManager`

- Resolve provider bằng Laravel container, cho phép dependency injection.
- Kiểm tra provider triển khai đúng contract; cấu hình sai phải báo lỗi rõ.
- Gọi provider theo thứ tự trong cấu hình và gộp kết quả thành danh sách phẳng.
- Mỗi item đầu ra của manager gồm `result` (DTO `SearchResult`),
  `providerLabel` (lấy từ `label()`) và `providerClass` (FQCN provider nguồn).
  Manager gắn metadata từ provider đang gọi, không yêu cầu provider tự lặp
  nhãn trong từng DTO. View chỉ hiển thị `providerLabel`, không hiển thị FQCN.
- Bản đầu nối kết quả theo thứ tự provider trong config, giữ thứ tự bản ghi
  do từng provider trả về; không tạo heading hoặc card riêng cho provider,
  chưa áp dụng xếp hạng độ liên quan giữa các nguồn.
- Provider giới hạn ở query; manager cũng giới hạn số item đưa ra giao diện.
- Manager nhận limit từ controller: màn hình dùng `search.limit_per_provider`,
  AJAX dùng `search.suggestions.limit_per_provider`. Sau khi tổng hợp, endpoint
  AJAX giới hạn danh sách ở `max_results`. Hai luồng dùng cùng provider,
  nhãn, quyền và logic tạo URL; không viết query riêng cho AJAX.
- Không query trực tiếp model của ứng dụng trong manager.
- Không nuốt exception thành “không có kết quả”; sử dụng cơ chế report lỗi
  của Laravel để tránh che giấu lỗi cấu hình hoặc query.

## 5. Endpoint và giao diện

Thêm route GET `/search`, tên `sag.search`, trong group middleware `admin`
hiện có. Prefix và middleware ngoài group tiếp tục lấy từ cấu hình SAG.

`SearchController`:

1. Kiểm tra trạng thái tính năng.
2. Validate `q` là chuỗi và không vượt `max_length`, sau đó trim khoảng trắng.
3. Với từ khóa rỗng hoặc ngắn hơn `min_length`, hiển thị hướng dẫn và không
   gọi provider.
4. Lấy user từ guard `admin`, gọi manager và render `sag::search.index`.

Navbar dùng `method="GET"`, `action="{{ route('sag.search') }}"` và input
`name="q"`. Giữ từ khóa trên trang search, giữ nút đóng của AdminLTE.

### Sidebar search dùng form GET

Áp dụng search của package cho ô `sidebar-search` trong cả
`src/resources/views/layouts/sidebar.blade.php` và
`stubs/views/layouts/sidebar.blade.php`:

- Chuyển wrapper hiện tại thành form `method="GET"`,
  `action="{{ route('sag.search') }}"`, input `type="search"`, `name="q"`
  và nút tìm kiếm `type="submit"`.
- Enter trong input hoặc click nút tìm kiếm chuyển đến màn hình search với
  từ khóa đã nhập. Dùng cùng provider, nhãn và link detail như navbar.
- Sidebar không có gợi ý, dropdown, debounce hoặc request AJAX khi nhập.
- Bỏ `data-widget="sidebar-search"` trên khung này để widget tìm menu của
  AdminLTE không can thiệp vào luồng search dữ liệu của package. Giữ các
  class giao diện `form-control-sidebar` và input group của theme.
- Asset `navbar-search.js` chỉ bind input navbar qua selector riêng, không
  bind tất cả input `type="search"` hoặc input có `name="q"`.
- Hiển thị/ẩn ô sidebar theo cùng điều kiện `search.enabled` và có provider;
  `search.suggestions.enabled` không ảnh hưởng sidebar.
- Giữ từ khóa chuỗi hợp lệ trên sidebar khi ở màn hình search, escape bằng
  Blade và không gán input dạng array vào thuộc tính `value`.
- Input có label/aria-label phù hợp; route lấy bằng named route, không
  hard-code prefix. Không cần endpoint hoặc asset riêng cho sidebar.

### AJAX search và gợi ý khi nhập trên navbar

Thêm route GET `/search/suggestions`, tên `sag.search.suggestions`, trong
cùng group middleware `admin`. Endpoint JSON dùng action `suggestions()` của
`SearchController`, tái sử dụng validation từ khóa và `SearchManager`.

- Tính năng search hoặc gợi ý bị tắt/chưa có provider: trả JSON HTTP 404.
- Từ khóa rỗng hoặc ngắn hơn `min_length`: trả HTTP 200 với `results: []`,
  không gọi provider.
- Input không hợp lệ hoặc quá dài: trả lỗi JSON HTTP 422, không redirect.
- Request gửi `Accept: application/json`; nếu session hết hạn, trả JSON 401.
  Điều chỉnh middleware `AuthAdmin` để request mong đợi JSON nhận 401, trong
  khi request trang HTML tiếp tục dùng redirect đăng nhập hiện có.
- Lỗi provider được report theo cơ chế Laravel; response lỗi không lộ chi
  tiết exception. Frontend hiển thị lỗi tải gợi ý, không coi là kết quả rỗng.

Response thành công dự kiến:

```json
{
  "query": "ABC",
  "results": [
    {
      "title": "Sản phẩm ABC",
      "description": "SKU: ABC-001",
      "url": "/admin/products/1",
      "provider_label": "Sản phẩm"
    }
  ]
}
```

Serialize các trường tường minh; không trả `providerClass`, model hoặc thuộc
tính nội bộ. URL dùng cùng helper của provider như màn hình search.

Hành vi frontend navbar:

1. Lắng nghe `input`, trim từ khóa và debounce theo cấu hình (mặc định 300 ms).
   Chỉ gửi AJAX khi đạt `min_length` và không vượt `max_length`. Chờ kết thúc
   composition khi người dùng đang nhập bằng bộ gõ.
2. Hủy request trước khi từ khóa thay đổi, đồng thời dùng request sequence
   hoặc đối chiếu từ khóa để response cũ không ghi đè gợi ý mới.
3. Hiển thị dropdown bên dưới input với trạng thái đang tải, kết quả, không
   có kết quả hoặc lỗi tải. Mỗi item có tiêu đề, mô tả tùy chọn và nhãn provider;
   danh sách phẳng, không chia nhóm.
4. Click item mở URL detail cùng tab. Dùng text node/`textContent` cho dữ liệu
   JSON, không ghép nội dung provider vào `innerHTML`; kiểm tra scheme URL
   trước khi gắn link giống quy tắc áp dụng cho trang kết quả.
5. Có link “Xem kết quả tìm kiếm” đến `sag.search` với từ khóa hiện tại,
   encode query bằng API URL chuẩn. Dropdown chỉ là gợi ý có giới hạn.
6. Enter hoặc click nút submit luôn submit form GET để chuyển sang màn hình
   search, giữ `q`; không chặn submit để chờ request AJAX hoặc tự mở item đầu.
7. Khi xóa từ khóa, đóng navbar search hoặc click ngoài vùng search: đóng
   dropdown và hủy request pending. Escape đóng dropdown; response đến muộn
   không được mở lại dropdown đã đóng.

Các link gợi ý có thể focus bằng Tab; Enter khi focus link mở trang detail,
Enter trong input submit form. Input có label và `aria-expanded`, liên kết
vùng gợi ý qua `aria-controls`; trạng thái tải/lỗi có vùng thông báo phù hợp.
Dropdown responsive theo khung search và có giới hạn chiều cao để cuộn.

Đặt JS trong asset riêng của package, include một lần trong layout và chỉ
khởi tạo khi có input search được bật. Navbar cung cấp URL endpoint và cấu
hình qua data attributes; không hard-code prefix. Đồng bộ markup/data
attributes ở source và stub. Khi JS không chạy hoặc AJAX lỗi, form GET vẫn
hoạt động. Gợi ý AJAX ở bản đầu áp dụng cho input navbar; form trong màn hình
search tiếp tục submit GET.

Trang kết quả kế thừa `sag::layouts.app`, hiển thị từ khóa và một danh sách
kết quả chung. Mỗi item có nhãn provider, tiêu đề, mô tả và link bản ghi.
Có trạng thái chưa nhập và không có kết quả. Hiển thị “Tối đa N kết quả từ
mỗi nguồn tìm kiếm” để người dùng hiểu giới hạn của bản đầu.

Dùng Blade escaped output cho mọi nội dung từ provider. URL phải do ứng dụng
tạo từ route hoặc nguồn tin cậy; không cho phép scheme như `javascript:`.

### Màn hình search hiển thị kết quả tìm kiếm

Package cung cấp màn hình search riêng tại `/{sag.prefix}/search`, dùng view
`src/resources/views/search/index.blade.php` (`sag::search.index`). Submit từ
navbar chuyển đến màn hình này; người dùng cũng có thể truy cập trực tiếp URL
và tìm kiếm ngay trên trang.

Màn hình kế thừa `sag::layouts.app` và dùng các thành phần AdminLTE hiện có:

1. Header có tiêu đề “Tìm kiếm” và breadcrumb về trang search hiện tại.
2. Form GET gồm input `name="q"`, giữ từ khóa đang tìm, nút “Tìm kiếm” và
   link “Xóa tìm kiếm” về `route('sag.search')` không có query string.
3. Khi đã tìm kiếm, hiển thị từ khóa và tổng số kết quả đang hiển thị, tính
   từ danh sách manager trả về; không gọi đây là tổng số bản ghi khớp trong database.
4. Hiển thị một card chứa danh sách kết quả chung, không chia nhóm provider.
5. Mỗi bản ghi gồm tiêu đề có link detail, badge/nhãn provider từ
   `providerLabel` và mô tả ngắn nếu có. Link sử dụng `SearchResult::url`,
   mở cùng tab. Không render mô tả rỗng.
6. Hiển thị giới hạn “Tối đa N kết quả từ mỗi nguồn tìm kiếm” theo cấu hình. Bản đầu chưa
   có phân trang hoặc nút “Xem thêm”.

Provider không có kết quả không tạo item hoặc placeholder trong danh sách.
Khi danh sách chung rỗng, hiển thị một thông báo không tìm thấy kết quả.

Ví dụ hiển thị:

```text
Kết quả tìm kiếm cho “ABC” — 2 kết quả đang hiển thị

Sản phẩm ABC                         [Sản phẩm]
SKU: ABC-001

Đơn hàng ABC-2026                    [Đơn hàng]
Mô tả ngắn của đơn hàng
```

| Trạng thái | Nội dung hiển thị | Gọi provider |
| --- | --- | --- |
| Chưa nhập hoặc chỉ có khoảng trắng | Hướng dẫn nhập từ khóa để tìm kiếm | Không |
| Từ khóa ngắn hơn `min_length` | Hướng dẫn số ký tự tối thiểu, giữ từ khóa trên form | Không |
| Input không hợp lệ hoặc quá dài | Lỗi validation cạnh input, không hiển thị kết quả cũ | Không |
| Có kết quả | Từ khóa, số kết quả hiển thị và danh sách chung có nhãn provider trên từng item | Có |
| Không có kết quả | Thông báo không tìm thấy và gợi ý đổi từ khóa | Có |

Controller xử lý validation GET theo cách render lại màn hình search với
lỗi và HTTP 422, tránh redirect về chính URL chứa query không hợp lệ. Input
`q` dạng array không được đưa trực tiếp vào thuộc tính `value`; chỉ giữ giá
trị chuỗi hợp lệ để render an toàn.

Form có label cho input và thông báo lỗi gắn với input. Bố cục responsive:
form có thể xuống dòng trên mobile, tiêu đề và mô tả dài được wrap. Không
thêm JavaScript riêng cho màn hình ở bản đầu vì search dùng submit GET.

Ứng dụng có thể override màn hình tại
`resources/views/vendor/sag/search/index.blade.php` theo cơ chế view namespace
hiện có. Ghi rõ đường dẫn này trong README để app tùy chỉnh giao diện.

## 6. Console command generate provider

### Signature dự kiến

```php
sag:generate_search_provider
    {name}
    {--model=}
    {--columns=}
    {--title=}
    {--route=}
    {--route-parameter=}
    {--force}
```

### Cách sử dụng

Sinh skeleton không phụ thuộc database:

```bash
php artisan sag:generate_search_provider Product
```

Sinh provider có query mẫu từ model:

```bash
php artisan sag:generate_search_provider Product \
  --model='App\Models\Product' \
  --columns=name,sku \
  --title=name \
  --route=sag.product.detail \
  --route-parameter=product
```

File đích: `app/Admin/Search/ProductSearchProvider.php`, namespace
`App\Admin\Search`. Dùng `app_path()` để hỗ trợ app có vị trí thư mục tùy chỉnh.

### Quy tắc generate

- `name` nhận basename StudlyCase, ví dụ `Product` hoặc
  `ProductSearchProvider`; chuẩn hóa về `ProductSearchProvider`. Không nhận
  namespace, đường dẫn hoặc ký tự traversal.
- Không ghi đè file đã tồn tại nếu thiếu `--force`.
- `--model` nhận FQCN Eloquent model; xác minh class tồn tại và kế thừa `Model`.
- `--columns` nhận danh sách cột phân cách bằng dấu phẩy, loại khoảng trắng
  và trùng lặp; `--title` nhận một cột hiển thị.
- `--columns` và `--title` yêu cầu `--model`; xác minh cột trên connection và
  table của model. Nếu không truy cập được schema, command báo lỗi rõ.
- `--route` nhận named route detail có sẵn, sinh `routeName()` trong provider.
  Không tự tạo trang detail hoặc route detail nếu ứng dụng chưa có.
- `--route-parameter` nhận tên placeholder cho bản ghi, sinh
  `routeParameters()` với ánh xạ tên tham số sang model. Option này yêu cầu
  `--route`; xác minh placeholder tồn tại trên route đã chọn.
- Chế độ query mẫu hỗ trợ route có đúng một tham số bắt buộc cho bản ghi.
  Nếu thiếu `--route-parameter`, lấy tên từ route khi chỉ có một placeholder;
  không suy đoán từ tên model. Chỉ sinh query mẫu khi ánh xạ đầy đủ các tham
  số bắt buộc và xác định được tham số bản ghi.
- Nếu route có binding field tường minh như `{product:slug}`, sinh ánh xạ
  giá trị cột tương ứng sau khi xác minh cột trên model. Trường hợp còn lại
  truyền model để dùng route key mặc định.
- Route nhiều tham số cần ứng dụng hoàn thiện `routeParameters()` trong
  skeleton; command in rõ các tham số cần ánh xạ và mặc định trả kết quả rỗng
  cho đến khi ứng dụng hoàn thiện.
- Nếu thiếu bất kỳ thông tin cần thiết cho query mẫu, sinh skeleton hợp lệ
  với TODO rõ ràng và mặc định trả mảng rỗng. Không tự đoán cột hoặc route.
- Provider Eloquent kế thừa `AbstractEloquentSearchProvider`; khi tạo DTO,
  dùng `resultUrl($record)` để tránh lặp logic resolve URL.
- Skeleton có TODO cho nhãn provider, quyền truy cập, tenant scope, query và ánh
  xạ tham số route detail.
- Query mẫu gom các điều kiện `OR` tìm kiếm trong một nhóm `where`, áp dụng
  `limit($limit)` trước khi lấy dữ liệu và giữ global scopes của model.
- Query mẫu chỉ là điểm bắt đầu; ứng dụng phải bổ sung policy/tenant scope
  theo nghiệp vụ trước khi đăng ký provider.
- Command chỉ tạo provider, không tạo model, route, migration hoặc sửa config.
- Sau khi generate, in đường dẫn file, các TODO còn lại và đoạn cấu hình
  đăng ký provider trong `sag.search.providers`, kèm hướng dẫn bật search.

### Stub

Tạo stub skeleton và stub provider Eloquent trong thư mục `stubs` của package.
Giữ code sinh ra dễ đọc và chỉnh sửa. Escape các giá trị đưa vào PHP source;
không nội suy trực tiếp input chưa được validate vào code.

## 7. Quyền và giới hạn dữ liệu

- Middleware `admin` đảm bảo người dùng đã đăng nhập; provider quyết định
  người dùng được tìm dữ liệu nào.
- Lọc quyền và tenant trước khi lấy kết quả, tránh rò rỉ tiêu đề hoặc mô tả.
- Provider trả mảng rỗng khi admin không có quyền truy cập nguồn dữ liệu.
- Trang đích vẫn phải kiểm tra quyền truy cập riêng.
- Query sử dụng bindings, không ghép từ khóa vào raw SQL.
- Search chỉ truy vấn provider đã đăng ký; giới hạn từ khóa và số kết quả.
- Bản đầu dùng `LIKE` cho query mẫu; kiểm tra hiệu năng trên dữ liệu thực tế
  trước khi mở rộng sang nhiều bảng lớn.

## 8. Danh sách thay đổi dự kiến

| File | Nội dung |
| --- | --- |
| `config/config.php` | Default config cho search |
| `src/Contracts/SearchProvider.php` | Contract cho ứng dụng triển khai |
| `src/Data/SearchResult.php` | DTO kết quả |
| `src/Search/AbstractEloquentSearchProvider.php` | Helper resolve URL từ route và tham số do provider khai báo |
| `src/Services/SearchManager.php` | Resolve provider và tổng hợp kết quả |
| `src/Http/Controllers/SearchController.php` | Validate và render search |
| `src/Http/Middleware/AuthAdmin.php` | Trả JSON 401 cho AJAX chưa xác thực |
| `routes/web.php` | Routes `sag.search` và `sag.search.suggestions` trong group admin |
| `src/resources/views/layouts/navbar.blade.php` | Form GET, vùng gợi ý AJAX và data attributes |
| `stubs/views/layouts/navbar.blade.php` | Đồng bộ navbar stub |
| `src/resources/views/layouts/sidebar.blade.php` | Form GET đến màn hình search, bỏ widget tìm menu AdminLTE |
| `stubs/views/layouts/sidebar.blade.php` | Đồng bộ sidebar search GET với source |
| `src/resources/assets/js/navbar-search.js` | Debounce, request AJAX và render gợi ý |
| `src/resources/views/layouts/app.blade.php` và `stubs/views/layouts/app.blade.php` | Include asset AJAX search trong layout sử dụng navbar |
| `src/resources/views/search/index.blade.php` | Màn hình search: form, danh sách kết quả chung có nhãn provider và các trạng thái hiển thị |
| `src/Console/GenerateSearchProviderCommand.php` | Command generate provider |
| `stubs/search/` | Các stub provider |
| `src/PackageServiceProvider.php` | Đăng ký console command |
| `tests/` | Kiểm thử manager, endpoint và generator |
| `README.md` | Hướng dẫn generate, đăng ký và sử dụng |

## 9. Thứ tự triển khai

1. Thêm config, contract và DTO.
2. Xây dựng manager với provider giả trong tests.
3. Thêm endpoint HTML, trang kết quả và nối form navbar/sidebar ở cả source/stub.
4. Thêm endpoint JSON, xác thực AJAX và asset gợi ý khi nhập trên navbar.
5. Thêm command, validation và các stub provider.
6. Viết hướng dẫn tích hợp, publish lại assets và cập nhật view override.
7. Chạy kiểm thử, kiểm tra thủ công trên ứng dụng sử dụng package.

## 10. Kiểm thử và tiêu chí hoàn thành

### Kiểm thử cần có

- Thiếu cấu hình search, thiếu `search.enabled`, `enabled = false` hoặc thiếu/
  rỗng providers: cả navbar và sidebar không render khung search; endpoints
  HTML/JSON trả 404. Không phát sinh lỗi truy cập config chưa tồn tại.
- Config ứng dụng cũ không có search sau khi merge default vẫn tắt tính năng;
  chỉ khi `enabled = true` và có provider mới render cả hai khung.
- Khi search bị ẩn, DOM không có icon/form/vùng gợi ý của navbar hoặc form
  sidebar và JS không khởi tạo request AJAX search.
- Chưa đăng nhập: endpoint được middleware admin bảo vệ.
- `q` rỗng, quá ngắn, quá dài hoặc dạng array: không gọi provider sai điều kiện.
- Provider được resolve qua container và nhận đúng admin, keyword, limit.
- Nhiều provider: danh sách phẳng đúng thứ tự, giới hạn kết quả và trạng thái
  rỗng; mỗi item được manager gắn đúng providerLabel/providerClass nguồn.
- Provider có nhãn rỗng hoặc chỉ khoảng trắng phải báo lỗi cấu hình; nhãn
  dịch hoặc trùng nhau vẫn giữ đúng định danh nguồn qua providerClass.
- Màn hình search render đúng layout, form GET và từ khóa; submit từ navbar
  và từ form trên trang đều đến `sag.search`.
- Số kết quả hiển thị khớp dữ liệu sau giới hạn; provider rỗng không tạo
  placeholder và danh sách rỗng có thông báo chung.
- Mỗi item hiển thị đúng nhãn provider đã escape; view không tạo heading/card
  theo provider và không hiển thị FQCN ra giao diện.
- Validation GET render màn hình với HTTP 422, không redirect lặp; lỗi cạnh
  input và từ khóa hợp lệ được giữ đúng, không render lại kết quả cũ.
- Kiểm tra thủ công bố cục mobile, nội dung dài, link detail và xóa tìm kiếm.
- Title/description chứa HTML được escape; URL không an toàn bị từ chối.
- Provider mẫu giữ global scopes và gom đúng điều kiện OR; fixture kiểm tra
  bản ghi ngoài scope không xuất hiện.
- Kết quả từ Product/Order dẫn đến đúng route detail tương ứng; link trong
  view sử dụng URL do provider tạo, hỗ trợ prefix cấu hình.
- Ánh xạ `{id}`, model route key dạng slug, binding `{product:slug}` và route
  nhiều tham số tạo đúng URL; route thiếu hoặc thiếu tham số phải báo lỗi rõ.
- Generator tạo đúng namespace/path, code PHP hợp lệ và hướng dẫn config.
- Tên không hợp lệ, model/cột/route sai: báo lỗi trước khi ghi file.
- File tồn tại: từ chối ghi đè; `--force` mới cho phép ghi đè.
- Thiếu option: skeleton hợp lệ, TODO rõ ràng, mặc định không trả dữ liệu.
- Generator ghi đúng `routeName()` và `routeParameters()`; option tham số
  route không hợp lệ bị từ chối trước khi ghi file.
- Source navbar và stub navbar cùng sử dụng API search mới.
- Sidebar source/stub có form GET, `name="q"`, nút submit và cùng route
  `sag.search`; điều kiện hiển thị nhất quán với navbar.
- Kiểm tra Enter/click ở sidebar chuyển đến màn hình kết quả với đúng từ khóa
  và prefix cấu hình; nhập ở sidebar không phát sinh request AJAX.
- Sidebar không còn `data-widget="sidebar-search"`; JS gợi ý chỉ bind navbar,
  không can thiệp sidebar hoặc form search trên trang kết quả.
- Endpoint AJAX trả đúng JSON và giới hạn, nhãn/URL tương ứng kết quả HTML;
  không serialize model hoặc providerClass.
- AJAX chưa đăng nhập trả JSON 401; tính năng bị tắt trả 404; input sai trả
  422; input rỗng/quá ngắn trả danh sách rỗng và không query provider.
- Kiểm tra debounce, bộ gõ, hủy request và response đảo thứ tự; dropdown không
  hiển thị kết quả cũ hoặc tự mở lại sau khi đóng.
- Kiểm tra dropdown loading/rỗng/lỗi, text chứa HTML, click detail và link
  xem kết quả với từ khóa có ký tự đặc biệt.
- Enter trong input và nút submit chuyển đến màn hình search với đúng `q`,
  kể cả khi AJAX đang tải, bị lỗi hoặc JS bị tắt; link gợi ý hỗ trợ Tab/Enter.

Chạy tests trong PHP container hiện có theo `AGENTS.md`, không dùng host PHP
CLI. Database tests chỉ chạy với MySQL development/test trong Docker và lấy
credentials từ môi trường container, không hard-code hoặc in credentials.

### Tiêu chí hoàn thành

- Laravel app generate được provider bằng SAG console.
- App chỉ cần hoàn thiện provider, đăng ký config và bật search để sử dụng.
- Thiếu cấu hình search hoặc `enabled = false`: ẩn toàn bộ khung search ở
  navbar và sidebar; mặc định không bật tính năng cho ứng dụng chưa cấu hình.
- Navbar submit đến đúng route với prefix cấu hình và hiển thị danh sách chung.
- Sidebar search submit đến cùng màn hình kết quả bằng GET, không có AJAX.
- Nhập từ khóa trên navbar hiển thị gợi ý AJAX có nhãn provider và link detail;
  submit vẫn chuyển đến màn hình search, không phụ thuộc request AJAX.
- Có màn hình search riêng với form tìm lại, nhãn provider trên mỗi item, số kết quả
  đang hiển thị và đầy đủ trạng thái chưa nhập, input lỗi, không có kết quả.
- Mỗi provider khai báo `label()` không rỗng; màn hình search hiển thị đúng
  nhãn đó trên mọi kết quả thuộc provider.
- Mỗi provider khai báo route detail; click kết quả mở đúng trang bản ghi,
  với tham số và route key tương ứng.
- Quyền/tenant thuộc trách nhiệm provider và được ghi rõ trong skeleton/docs.
- Tests liên quan và các checks bắt buộc của package đều pass.

## 11. Nâng cấp sau bản đầu

- Phân trang danh sách kết quả chung khi có nhu cầu.
- Provider sử dụng full-text hoặc search engine cho dữ liệu lớn mà không đổi
  contract của navbar.
- Tùy chọn `search.route` để app sử dụng endpoint riêng nếu có nhu cầu thực tế;
  chưa đưa vào bản đầu để giữ một luồng tích hợp rõ ràng.
