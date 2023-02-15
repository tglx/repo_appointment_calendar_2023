CREATE TABLE `appointments` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`person_name` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'latin1_swedish_ci',
	`person_email` VARCHAR(50) NOT NULL DEFAULT '' COLLATE 'latin1_swedish_ci',
	`date` DATE NOT NULL,
	`hour_begin` TIME NOT NULL,
	`hour_end` TIME NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `date_hour_begin` (`date`, `hour_begin`) USING BTREE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
AUTO_INCREMENT=4
;


CREATE TABLE `available_time_slots` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`date` DATE NOT NULL,
	`hour_begin` TIME NOT NULL,
	`hour_end` TIME NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	UNIQUE INDEX `date_hour_begin` (`date`, `hour_begin`) USING BTREE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
ROW_FORMAT=DYNAMIC
AUTO_INCREMENT=29
;
