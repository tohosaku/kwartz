----------------------------------------------------------------------
-- DDL for MySQL
--   generated by kwatable with template 'ddl-mysql.eruby'
--   at Mon Aug 07 07:12:56 JST 2006
----------------------------------------------------------------------

-- group master table
drop table if exists groups;
create table groups (
   id                   integer              auto_increment primary key,
   name                 varchar(63)          not null unique,
   `desc`               text                 
);

-- member master table
drop table if exists members;
create table members (
   id                   integer              auto_increment primary key,
   name                 varchar(63)          not null unique,
   `desc`               text                 ,
   email                varchar(63)          ,
   gender               enum('M', 'W', 'X')  ,
   birth                date                 ,
   group_id             integer              not null,  -- references groups(id)
   created_at           timestamp            not null,
   modified_at          timestamp            not null
);