<?php
namespace FastFood\Controllers;

use FastFood\Middlewares\AuthMiddleware;
use FastFood\Constants\HttpStatusCodes;

/**
 * Image Upload Controller
 */
class ImageUploadController extends BaseController {
    public function upload(): void {
        AuthMiddleware::requireAdminOrStaff();

        $file = $_FILES['image'] ?? null;
        if (!$file && !empty($_FILES['file'])) {
            $file = $_FILES['file'];
        }

        if (!$file || empty($file['tmp_name'])) {
            $rawBody = file_get_contents('php://input');
            if (empty($rawBody)) {
                $this->sendError('Không tìm thấy tệp ảnh tải lên.', HttpStatusCodes::BAD_REQUEST);
            }
            // Raw binary upload
            $contentType = $_SERVER['CONTENT_TYPE'] ?? 'image/jpeg';
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $ext = $extMap[$contentType] ?? 'jpg';
            $filename = 'img_' . uniqid() . '.' . $ext;
            $rootUploadDir = dirname(__DIR__, 3) . '/assets/uploads';
            if (!is_dir($rootUploadDir)) mkdir($rootUploadDir, 0777, true);
            file_put_contents($rootUploadDir . '/' . $filename, $rawBody);
            
            $frontendDir = dirname(__DIR__, 3) . '/frontend/assets/uploads';
            if (is_dir($frontendDir)) copy($rootUploadDir . '/' . $filename, $frontendDir . '/' . $filename);

            $this->sendCreated('Tải ảnh thành công!', ['url' => '/assets/uploads/' . $filename]);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $this->sendError('Chỉ hỗ trợ tệp ảnh JPG, PNG hoặc WebP.', HttpStatusCodes::BAD_REQUEST);
        }

        $filename = 'img_' . uniqid() . '.' . $ext;
        $rootUploadDir = dirname(__DIR__, 3) . '/assets/uploads';
        if (!is_dir($rootUploadDir)) mkdir($rootUploadDir, 0777, true);

        if (!move_uploaded_file($file['tmp_name'], $rootUploadDir . '/' . $filename)) {
            $this->sendError('Không thể lưu tệp ảnh lên máy chủ.', HttpStatusCodes::INTERNAL_SERVER_ERROR);
        }

        $frontendDir = dirname(__DIR__, 3) . '/frontend/assets/uploads';
        if (is_dir($frontendDir)) copy($rootUploadDir . '/' . $filename, $frontendDir . '/' . $filename);

        $this->sendCreated('Tải ảnh thành công!', ['url' => '/assets/uploads/' . $filename]);
    }
}

