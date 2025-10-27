# BTMC Gold Price

Plugin WordPress giúp lấy dữ liệu giá vàng BTMC theo thời gian thực thông qua API chính thức và hiển thị trên website bằng shortcode.

## Tính năng chính

- Kết nối tới API giá vàng BTMC và cache dữ liệu theo khoảng thời gian cấu hình.
- Hiển thị bảng hoặc danh sách giá vàng với shortcode `[btmc_gold_price]`.
- Tùy chỉnh số chữ số thập phân, ký tự ngăn cách, hậu tố tiền tệ và trạng thái hiển thị chênh lệch.
- Trang cài đặt trong phần **Cài đặt → BTMC Gold Price** cho phép nhập endpoint, token/api key, thời gian cache.
- Hỗ trợ thêm lớp CSS tùy chỉnh và tùy chọn tắt CSS mặc định khi cần tự style.

## Sử dụng shortcode

```text
[btmc_gold_price]
```

### Thuộc tính hỗ trợ

| Thuộc tính | Mô tả | Giá trị mặc định |
|------------|-------|------------------|
| `product` | Lọc theo tên loại vàng (so khớp một phần, không phân biệt hoa thường). | _(trống)_ |
| `view` | Kiểu hiển thị `table` hoặc `list`. | `table` |
| `show_change` | `true` / `false` để bật tắt cột chênh lệch mua/bán. | `true` |
| `show_updated` | Hiển thị thời gian cập nhật cuối. | `true` |
| `class` | Thêm CSS class vào wrapper. | _(trống)_ |
| `currency_suffix` | Hậu tố hiển thị sau giá (ví dụ `VND/lượng`). | Theo cấu hình trong trang cài đặt |
| `force_refresh` | `true` để bỏ qua cache trong lần tải này. | `false` |
| `decimals` | Số chữ số thập phân hiển thị. | `0` |
| `decimal_separator` | Ký tự phân cách phần thập phân. | `,` |
| `thousands_separator` | Ký tự phân cách phần nghìn. | `.` |

Ví dụ shortcode hiển thị danh sách, bỏ cột chênh lệch:

```text
[btmc_gold_price view="list" show_change="false" currency_suffix="VND"]
```

## Cài đặt

1. Sao chép thư mục `wordpress-btmc-gold-price` vào thư mục `wp-content/plugins` của WordPress.
2. Kích hoạt plugin trong trang **Plugins**.
3. Mở **Cài đặt → BTMC Gold Price** để nhập endpoint và các thông số xác thực do BTMC cung cấp.
4. Dán shortcode vào vị trí mong muốn.

## Ghi chú

- Endpoint mặc định sử dụng `https://apics2.btmc.vn/api/price/gold`. Nếu BTMC thay đổi tài liệu bạn có thể cập nhật trực tiếp trong trang cài đặt.
- Plugin sử dụng `wp_remote_get` và sẽ kế thừa các hạn chế truy cập mạng của host. Nếu máy chủ chặn outbound HTTPs cần cấu hình lại firewall/proxy.
