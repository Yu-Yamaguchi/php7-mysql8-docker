set global local_infile = 1;

create table if not exists m_sample(
  `code` char(3) not null,
  `name` varchar(80) not null,
  primary key(`code`)
) engine=innodb default charset=utf8;
