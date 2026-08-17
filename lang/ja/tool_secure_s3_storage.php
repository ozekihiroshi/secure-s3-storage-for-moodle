<?php
// This file is part of Secure S3 Storage for Moodle.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Japanese language strings.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['awsconfiguration'] = 'AWS設定';
$string['awsconfiguration_desc'] = 'AWS SDKのデフォルト認証情報プロバイダチェーンを使用します。長期AWSアクセスキーはMoodle設定に保存しません。';
$string['backupsource'] = 'コースバックアップ元';
$string['backupsource_desc'] = '設定したディレクトリにある、生成完了済みのMoodleコースバックアップだけを転送対象にします。';
$string['bucket'] = 'S3バケット';
$string['bucket_desc'] = 'Amazon S3の転送先バケットです。実行環境のIDには事前にアクセス権限が必要です。';
$string['pluginname'] = 'Secure S3 Storage';
$string['prefix'] = 'S3プレフィックス';
$string['prefix_desc'] = 'このプラグイン専用のオブジェクトプレフィックスです。Retentionはこの範囲外を操作しません。';
$string['privacy:metadata'] = 'Secure S3 Storageは、アーカイブのファイル名、サイズ、チェックサム、オブジェクトキー、転送状態、時刻を記録します。コースバックアップには個人データが含まれる場合があり、設定したS3転送先へ送信されます。';
$string['region'] = 'AWSリージョン';
$string['region_desc'] = '転送先バケットが存在するAWSリージョンです。';
$string['sourcedirectory'] = 'バックアップディレクトリ';
$string['sourcedirectory_desc'] = 'Moodle自動コースバックアップが使用するサーバー上の絶対パスです。ファイルを調べる前にパスを検証します。';
$string['stabilityseconds'] = '安定性の観測時間';
$string['stabilityseconds_desc'] = 'スケジュールタスクの複数回実行にわたり、ファイルサイズと更新日時が同じ状態を維持する必要がある秒数です。最小値は1秒です。';
$string['task_already_running'] = '別のSecure S3 Storageスキャンが実行中です。';
$string['task_configuration_error'] = 'Secure S3 Storageの設定が無効なため、転送を停止しました。';
$string['task_file_failed'] = '{$a}の転送に失敗しました。ローカルアーカイブは保持されています。';
$string['task_file_observed'] = '{$a}を観測しました。安定性の観測時間が経過するまで待機します。';
$string['task_file_transferred'] = '{$a}を転送し、整合性を検証しました。';
$string['task_no_files'] = '対象となるMoodleバックアップアーカイブはありません。';
$string['task_transfer_course_backups'] = '生成済みコースバックアップをAmazon S3へ転送する';
$string['transferenabled'] = '定期転送を有効にする';
$string['transferenabled_desc'] = '初期状態では無効です。バックアップ元とS3転送先を検証した後にだけ有効にします。';
$string['secure_s3_storage:manage'] = 'Secure S3 Storageの設定とバックアップ転送を管理する';
$string['privacy:metadata:s3'] = '設定されたS3互換ストレージへMoodleコースバックアップアーカイブを送信します。';
$string['privacy:metadata:s3:archive'] = 'Moodleバックアップ設定で選択されたコース参加者データを含む可能性があるコースバックアップアーカイブです。';
$string['privacy:metadata:transfer'] = '観測および転送したMoodleバックアップアーカイブの監査記録です。';
$string['privacy:metadata:transfer:checksum'] = 'アーカイブのSHA-256チェックサムです。';
$string['privacy:metadata:transfer:filename'] = 'ローカルアーカイブのファイル名です。';
$string['privacy:metadata:transfer:filesize'] = 'アーカイブのサイズです。';
$string['privacy:metadata:transfer:objectkey'] = '転送先S3オブジェクトキーです。';
$string['privacy:metadata:transfer:status'] = '転送状態です。';
$string['privacy:metadata:transfer:timecreated'] = 'アーカイブが最初に記録された時刻です。';
