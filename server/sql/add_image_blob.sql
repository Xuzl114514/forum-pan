-- 修改 attachments 表，添加 data 字段存储二进制数据
ALTER TABLE `attachments` ADD COLUMN `file_data` LONGBLOB NULL AFTER `file_size`;

-- 创建新表存储图片数据（可选，如果需要分离存储）
CREATE TABLE IF NOT EXISTS `image_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attachment_id` int(11) NOT NULL,
  `image_data` LONGBLOB NOT NULL,
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attachment_id` (`attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
