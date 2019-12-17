# docker 19 + nginx 1.17.6 + PHP(PHP-FPM) 7.4の環境構築

## 環境情報

`2019/12/13 時点`

- MacOS X 10.15.1（19B88）
- Docker 19.03.5
- nginx 1.17.6
- php-fpm 7.4.0
- MySQL 8.0.18

## 事前準備

Mac用の`docker-for-mac`をインストールする必要があります。
インストールする際、Docker Hubのアカウントが必要になりますので、事前にDocker Hubへのアカウント登録を済ませておいてください。

https://docs.docker.com/docker-for-mac/install/

```sh
$ docker --version
Docker version 19.03.5, build 633a0ea
```

## 環境構築

基本的にはdocker-composeを使って環境構築を進めていきます。

### nginxの構築

以下のディレクトリ構成を前提に環境構築を進めていきます。

```shell-session
.
├── docker-compose.yml
├── php-docker.md
└── web
    ├── conf
    │   └── default.conf
    └── src
        └── index.html
```

#### default.confファイルの内容

```config
server {
  listen 80;
  server_name 127.0.0.1;

  #ドキュメントルートの設定
  root  /var/www/;
  index index.html index.htm;

  access_log /var/log/nginx/access.log;
  error_log  /var/log/nginx/error.log;
}
```

#### index.htmlファイルの内容

```html
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>ようこそDocker</title>
</head>

<body>
  <h1>ようこそDocker！</h1>
</body>
</html>
```

#### docker-compose.ymlファイルの内容

```yaml
version: '3'
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"
    volumes:
      - ./web/conf/default.conf:/etc/nginx/conf.d/default.conf
      - ./web/src:/var/www/
```

- version
  - Docker 19.03.5に対応しているdocker-composeのファイルフォーマットは`3.7`なので、yamlのversionには`3`を指定する。
  - 対応バージョンの確認は[こちら](https://docs.docker.com/compose/compose-file/#compose-and-docker-compatibility-matrix)から確認してください。
- services -> web は、任意の名前をつけられます。今回はnginxをWebサーバーとして利用する環境構築となり、わかりやすい名称として`web`と定義しています。
- services -> web -> image に設定している `nginx:latest` は、[docker hubのnginx](https://hub.docker.com/_/nginx)でどのバージョンに対応しているかなどが確認できます。
  - ここではlatestとしていますが、`1.17.6`と指定しても同じ結果になります。（2019/12/13時点では。）
- services -> web -> ports の`80:80`は、ホスト（ここではMacPC）で受け付けたポート`80`へのリクエストを、Dockerコンテナの`80`ポートに転送するという設定です。`ポートフォワーディング`
- volumes ホスト側で編集したソースやconfなどをDockerコンテナと同期するための設定。

#### nginxの環境構築が正常に完了しているか動作確認

カレントディレクトリを`docker-compose.yml`のあるディレクトリに移動して、下記コマンドを実行します。

```shell-session
$ docker-compose up -d

Creating network "php-docker_default" with the default driver
Pulling web (nginx:latest)...
latest: Pulling from library/nginx
000eee12ec04: Pull complete
eb22865337de: Pull complete
bee5d581ef8b: Pull complete
Digest: sha256:50cf965a6e08ec5784009d0fccb380fc479826b6e0e65684d9879170a9df8566
Status: Downloaded newer image for nginx:latest
Creating php-docker_web_1 ... done
```

nginxのDockerコンテナが正常に動作しているか確認するため以下のURLにアクセスします。
http://127.0.0.1/

「ようこそDocker！」というページ（index.htmlで作ったページ）が表示されればOKです。

いったん起動したnginxのコンテナを停止します。

```shell-session
$ docker-compose stop

Stopping php-docker_web_1 ... done
```

ここまでで、dockerによるnginx環境の構築がいったん完了です。


### php-fpm 7.4の環境構築

以下のディレクトリ構成を前提に環境構築を進めていきます。

```shell-session
.
├── docker-compose.yml
├── php
│   └── src
│       ├── html
│       └── index.php
├── src
│   └── html
└── web
    ├── conf
    │   └── default.conf
    └── src
        └── index.html
```

#### default.confファイルの内容

```config
server {
  listen 80;
  server_name 127.0.0.1;

  #ドキュメントルートの設定
  root  /var/www/;
  index index.php index.html index.htm;

  location / {
    # 指定された順序でfileやdirの存在を確認し、最初に見つかったものを返却する。
    # いずれも無かった場合は、最後に指定されたパスに遷移する。
    try_files $uri $uri/ /index.php$is_args$args;
  }

  location ~ \.php$ {
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    # php-fpmのコンテナである「app」のポート9000に対してリクエストのパスを設定
    fastcgi_pass   app:9000;
    # 1台のサーバーでnginx+php-fpmを動作させる場合、Unixソケットの方が高速に動作するため、
    # 以下の設定をするが、今回はnginxとphp-fpmを別のコンテナにしているため、ポートでの接続となっている。
    # fastcgi_pass   unix:/var/run/php-fpm/php-fpm.sock
    fastcgi_index  index.php;
    include        fastcgi_params;
    fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param  PATH_INFO $fastcgi_path_info;
  }

  access_log /var/log/nginx/access.log;
  error_log  /var/log/nginx/error.log;
}

```

#### index.phpファイルの内容

```php
<?php
phpinfo();
```

### docker-compose.ymlファイルの内容

```yaml
version: '3'
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"
    depends_on:
      - app
    volumes:
      - ./web/conf/default.conf:/etc/nginx/conf.d/default.conf
      #- ./web/src:/var/www/ PHP側でソースを管理するためコメントアウト
  app:
    image: php:7.4.0-fpm
    volumes:
      - ./php/src:/var/www/
```

カレントディレクトリを`docker-compose.yml`のあるディレクトリに移動して、下記コマンドを実行します。

```sh
$ docker-compose up -d
```

http://127.0.0.1/

にアクセスしてphpinfo()の実行結果が確認できれば完了。
php 7.4.0 って2019年11月28日にビルドされたんですねぇ

### MySQL 8.0.18の環境構築

上記までで nginx + php での環境構築が完了しているので、あとはデータベースサーバーであるMySQLを追加していきます。
mysqlフォルダ配下の構成が増えているファイルやフォルダです。
また、PHP7.4のDockerコンテナイメージには、デフォルトでPDO（PHP Data Objects）のMySQLドライバがセットアップされていません。（デフォルトはsqliteのみ）
そのため、phpフォルダ 配下にDockerfileを新規で追加して、phpの環境構築を少し変更する必要があります。

詳細は下記記事でまとめておきました。
[docker-compose＋MySQL8（8.0.18）＋php-fpm(7.4)のPDOでcould not find driverが発生](https://qiita.com/You_name_is_YU/items/2aa9649959fb5dbf09c2)

```shell-session
.
├── docker-compose.yml
├── mysql
│   └── init
│       ├── 10_ddl.sql
│       ├── 20_data_load.sh
│       └── data.csv
├── php
│   ├── Dockerfile
│   └── src
│       └── index.php
└── web
    └── conf
        └── default.conf
```

#### docker-compose.ymlファイルの内容

ここでのポイントは、mysqlのコンテナの`volumes`で指定している２つのマウント指定です。
- １つはMySQLのデータベースに加えた様々な変更を永続化する（コンテナの起動の度に初期化されないようにする）設定
- もう１つはデータベースを初回だけ初期化するための設定（初回コンテナ起動時に１度だけデータベースを初期化するために実行されるスクリプトを配置するディレクトリをマウントしています。）

また、PHPのコンテナの指定で`image`の指定から、Dockerfileを利用した構築に変更するため、`build`の指定を追加しています。

```yaml
version: '3'
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"
    depends_on:
      - app
    volumes:
      - ./web/conf/default.conf:/etc/nginx/conf.d/default.conf
  app:
    #https://hub.docker.com/_/php/
    #imageを指定してphpのコンテナを構築するのではなく、Dockerfileを利用した構築方法に変更
    #image: php:7.4.0-fpm
    build: ./php
    volumes:
      - ./php/src:/var/www/
  mysql:
    #https://hub.docker.com/_/mysql
    #image: mysql:8.0.18 <-- latestだとこのバージョン（2019/12/13時点）
    image: mysql:latest
    environment:
      #イメージの起動時に作成するデータベースの名前
      MYSQL_DATABASE: yudb
      #このユーザはMYSQL_DATABASE変数で指定されたデータベースに対してスーパーユーザとしての権限(GRANT ALL)を保持する
      MYSQL_USER: mysqluser
      #MYSQL_USERのパスワード
      MYSQL_PASSWORD: mysqluser00
      # MySQLにおけるスーパーユーザであるrootアカウントに設定するためのパスワード
      MYSQL_ROOT_PASSWORD: mysqluser000
    ports:
      - "3306:3306"
    volumes:
      # /var/lib/mysqlをvolumesで追加する意図として、MySQLのデータベースに加えた変更を永続化するため。
      # 例えば、「yudb」という名前でデータベースを作成し、mysqluserを作成し、DDLを流してテーブルを作成し、
      # LOAD DATA LOCAL INFILEによってCSVファイルで初期データをロードした場合、/var/lib/mysqlをvolumesに指定しないと、
      # MySQLのコンテナを起動するたびに初期化処理が走るようになります。
      - ./mysql/var_lib_mysql:/var/lib/mysql
      # /docker-entrypoint-initdb.d/配下は、Dockerコンテナが初回起動（初期化）される際に１度だけ実行されるスクリプトなどを配置
      # *.sh / *.sql / *.sql.gzの拡張子のファイルはファイル名の昇順に実行される。
      - ./mysql/init:/docker-entrypoint-initdb.d
```

#### Dockerfileの内容

```dockerfile
FROM php:7.4.0-fpm

RUN apt-get update \
    && docker-php-ext-install pdo_mysql
```

#### 10_ddl.sqlファイルの内容

```sql
set global local_infile = 1;

create table if not exists m_sample(
  `code` char(3) not null,
  `name` varchar(80) not null,
  primary key(`code`)
) engine=innodb default charset=utf8;
```

#### 20_data_load.shファイルの内容

ここでは、shellからMySQLでCSVファイルをテーブルにロードするための`LOAD DATA LOCAL INFILE`を実行する方法を採用しています。

例えば、上記`10_ddl.sql`ファイルに以下のような記述を追加することでもCSVファイルをロードできると思いますが、、、

```sql
LOAD DATA LOCAL INFILE '/docker-entrypoint-initdb.d/data.csv' INTO TABLE m_sample FIELDS TERMINATED BY ',' ENCLOSED BY '"';
```

MySQL8のバージョンから、`LOAD DATA LOCAL INFILE`を実行すると以下のようなエラーが発生するようになりました。

```
The used command is not allowed with this MySQL version
```

原因や対応方法などの詳細な内容は以下の記事でまとめています。
[docker-compose＋MySQL8（8.0.18）で初期データをCSVロードしようとするとエラー（The used command is not allowed with this MySQL version）に](https://qiita.com/You_name_is_YU/items/6d87f7664c947df84dc1)

これらの理由から、shellを利用したCSVファイルのデータロードを選択しています。

```shell
mysql -umysqluser -pmysqluser00 yudb --local-infile=1 -e "LOAD DATA LOCAL INFILE '/docker-entrypoint-initdb.d/data.csv' INTO TABLE m_sample FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n'"
```

#### data.csvファイルの内容

参考までにCSVファイルの内容も載せておきます。

```csv
"001","test001"
"002","test002"
"003","test003"
"004","test004"
```

#### index.phpの内容

index.phpを変更し、MySQLに登録したデータをvar_dumpで画面に出力するプログラムを作ります。

```php
<?php

$dsn = 'mysql:host=mysql;dbname=yudb';
$user = 'mysqluser';
$password = 'mysqluser00';

try {
  $dbh = new PDO($dsn, $user, $password);

  $sql = "select * from m_sample";

  foreach ($dbh->query($sql, PDO::FETCH_ASSOC) as $row) {
    var_dump($row);
  }
} catch (PDOException $e) {
  var_dump($e);
}
```


#### MySQLの環境構築が完了したか確認

```shell-session
$ docker-compose up -d
Creating network "php-docker_default" with the default driver
Creating php-docker_app_1   ... done
Creating php-docker_mysql_1 ... done
Creating php-docker_web_1   ... done


$ docker-compose exec mysql bash
root@83e05bbf0a6a:/# mysql -umysqluser -p
Enter password:
Welcome to the MySQL monitor.  Commands end with ; or \g.
Your MySQL connection id is 8
Server version: 8.0.18 MySQL Community Server - GPL

Copyright (c) 2000, 2019, Oracle and/or its affiliates. All rights reserved.

Oracle is a registered trademark of Oracle Corporation and/or its
affiliates. Other names may be trademarks of their respective
owners.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

mysql> connect yudb
Reading table information for completion of table and column names
You can turn off this feature to get a quicker startup with -A

Connection id:    9
Current database: yudb

mysql> show tables;
+----------------+
| Tables_in_yudb |
+----------------+
| m_sample       |
+----------------+
1 row in set (0.00 sec)

mysql> select * from m_sample;
+------+---------+
| code | name    |
+------+---------+
| 001  | test001 |
| 002  | test002 |
| 003  | test003 |
| 004  | test004 |
+------+---------+
4 rows in set (0.00 sec)
```

#### PHPからMySQLのデータが参照できているか確認

http://127.0.0.1

以下の取得結果が画面に表示されたことを確認。

```php
array(2) { ["code"]=> string(3) "001" ["name"]=> string(7) "test001" } array(2) { ["code"]=> string(3) "002" ["name"]=> string(7) "test002" } array(2) { ["code"]=> string(3) "003" ["name"]=> string(7) "test003" } array(2) { ["code"]=> string(3) "004" ["name"]=> string(7) "test004" }
```

これで、一通りの環境構築が完了しました。
