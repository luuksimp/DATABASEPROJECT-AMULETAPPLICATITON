# ตลาดพระเครื่อง (PHP + MySQL)

## วิธีติดตั้ง
1. ติดตั้ง XAMPP / Laragon (PHP 8.1 ขึ้นไป + MySQL)
2. คัดลอกโฟลเดอร์นี้ไปไว้ที่ `htdocs/amulet-market`
3. เปิด phpMyAdmin แล้ว Import ไฟล์ `database.sql`
4. คัดลอก `config.example.php` เป็น `config.php` แล้วแก้ user/password ของ MySQL (`config.php` ไม่ถูกเก็บใน Git)
5. เปิด `http://localhost/amulet-market/`

## ฟีเจอร์
- สมัครสมาชิก / เข้าสู่ระบบ (รหัสผ่าน hash ด้วย password_hash)
- ฟีดโพสต์ (ล่าสุด / ยอดฮิต 7 วัน), สร้างโพสต์พร้อมรูป, แนบพระที่ลงขาย
- ไลก์ / โหวตแท้ / โหวตเก๊ (1 คนต่อ 1 โพสต์, กดซ้ำเพื่อยกเลิก), คอมเมนต์
- ตลาดพระ: ค้นหา/กรองหมวดหมู่, หน้ารายละเอียด, ลงขายหลายรูป
- สั่งซื้อ (กันซื้อซ้ำด้วย transaction), ชำระผ่านตัวกลาง (จำลอง), จัดส่ง/เลขพัสดุ, ยืนยันรับของ
- รีวิว 1-5 ดาว + แจ้งพระเก๊/ไม่ได้รับของ, คำนวณคะแนนผู้ขายอัตโนมัติ

## หมายเหตุ
- การชำระเงินเป็นการจำลอง — ใช้งานจริงต้องเชื่อม payment gateway / escrow
- ยังไม่มีหน้า Admin สำหรับตรวจข้อพิพาทและยืนยันตัวตน (แก้ที่ตาราง `users.Verification_Status` และ `reviews_disputes` ผ่าน phpMyAdmin ไปก่อน)
- ตั้ง Role เป็น Admin ได้ด้วย SQL: `UPDATE users SET Role='Admin' WHERE Username='...'`
