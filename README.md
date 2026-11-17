# 勤怠管理アプリ

## アプリケーション概要

Laravel 10を使用して開発した勤怠管理アプリケーションです。

一般ユーザーの出退勤・休憩の記録、勤怠一覧・詳細の確認、勤怠修正申請、管理者による勤怠管理・申請承認に対応しています。

また、月別の勤怠レポート表示と、勤怠情報を扱うAPI機能を実装しています。

## 使用技術

- PHP 8系（Composer要件：8.1以上、9.0未満）
- Laravel 10
- Laravel Fortify
- Laravel Sanctum
- MySQL 8.4
- Docker / Laravel Sail
- phpMyAdmin
- Mailpit
- Vite 5
- PHPUnit 10

## 環境構築

### 1. リポジトリの取得

```bash
git clone git@github.com:kurahasy703/attendance-app.git
cd attendance-app
```

※ SSH接続を設定していない場合は、HTTPSでクローンしてください。

### 2. PHPライブラリのインストール

Dockerを起動した状態で、Composerを利用して依存ライブラリをインストールします。

ローカルにComposerがある場合：

```bash
composer install
```

Composerがない場合は、公式Composerイメージなどを利用してインストールしてください。

### 3. 環境変数の設定

```bash
cp .env.example .env
```

`.env` のデータベース設定を確認してください。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

※ `password` はローカル開発用の設定例です。

### 4. Dockerコンテナの起動

```bash
./vendor/bin/sail up -d
```

### 5. アプリケーションキーの生成

```bash
./vendor/bin/sail artisan key:generate
```

### 6. データベースの作成

```bash
./vendor/bin/sail artisan migrate
```

### 7. テストデータの登録

```bash
./vendor/bin/sail artisan db:seed
```

Seederを実行すると、一般ユーザー2名と管理者1名、および勤怠・休憩のサンプルデータが登録されます。

確認済みのデータ件数：

| ユーザー          | 勤怠件数 |
| ----------------- | -------: |
| user1@example.com |     92件 |
| user2@example.com |     90件 |
| user3@example.com |     90件 |
| 合計              |    272件 |

### 8. フロントエンドのビルド

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

### 9. アプリケーションへのアクセス

- 一般ユーザーログイン：`http://localhost/login`
- 管理者ログイン：`http://localhost/admin/login`
- phpMyAdmin：`http://localhost:8080`
- Mailpit：`http://localhost:8025`

※ ポートを変更している場合は、変更後の番号を使用してください。

## テストアカウント

| 種別          | メールアドレス    | パスワード |
| ------------- | ----------------- | ---------- |
| 一般ユーザー1 | user1@example.com | password   |
| 一般ユーザー2 | user2@example.com | password   |
| 管理者        | user3@example.com | password   |

これらはSeederで登録する開発・動作確認用アカウントです。

## API

Laravel Sanctumを利用したAPI認証と、勤怠情報を扱うAPIを実装しています。

APIのエンドポイント、リクエスト・レスポンス形式については、API仕様書との最終照合後に記載します。

## 自動テスト

```bash
./vendor/bin/sail test --do-not-cache-result
```
