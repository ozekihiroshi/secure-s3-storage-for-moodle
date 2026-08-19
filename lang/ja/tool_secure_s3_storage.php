<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
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
$string['contentbackup'] = 'サイトコンテンツファイルプール';
$string['contentbackup_desc'] = '組込みDB回復スナップショットから参照される空でないコンテンツオブジェクトを保護します。通常のファイル保存先は変更せず、ローカルおよびS3のオブジェクトを削除しません。';
$string['contentbatchsize'] = '1回に処理するコンテンツオブジェクト数';
$string['contentbatchsize_desc'] = '1回のスケジュール実行で処理する重複しないファイルプールオブジェクトの上限です。許容範囲は1から1000です。';
$string['contenttransferenabled'] = 'コンテンツオブジェクトの定期転送を有効にする';
$string['contenttransferenabled_desc'] = '初期状態では無効で、組込みDB producerが必要です。S3権限、容量、費用、隔離復元手順を検証した後にだけ有効にします。';
$string['databaseartifactdirectory'] = 'データベース成果物ディレクトリ';
$string['databaseartifactdirectory_desc'] = '生成完了済みのデータベースpayloadとmanifestの組を置く、読み取り専用の絶対パスです。このプラグインはDB dumpを生成しません。';
$string['databasebackupsource'] = 'データベースバックアップ成果物';
$string['databasebackupsource_desc'] = '権限を分離したproducerが一貫性のあるDB dumpを生成し、Secure S3 Storageは完成済み成果物の検証と転送だけを行います。';
$string['databaseproducermode'] = 'DBバックアップ生成方式';
$string['databaseproducermode_builtin'] = 'Moodle内蔵producer（MariaDB/MySQL）';
$string['databaseproducermode_desc'] = '標準モードではmoodledata配下にMoodle論理DBバックアップを生成します。外部producerモードでは、権限分離された別プロセスが生成した成果物のみを検証して転送します。方式を変更してもバックアップは自動的に有効になりません。';
$string['databaseproducermode_external'] = '外部producer（高度な権限分離）';
$string['databasetransferenabled'] = 'データベース成果物の定期転送を有効にする';
$string['databasetransferenabled_desc'] = '初期状態では無効です。producer、Cronの読み取り専用mount、S3権限、隔離復元試験を検証した後にだけ有効にします。';
$string['pluginname'] = 'Secure S3 Storage';
$string['prefix'] = 'S3プレフィックス';
$string['prefix_desc'] = 'このプラグイン専用のオブジェクトプレフィックスです。Retentionはこの範囲外を操作しません。';
$string['privacy:metadata'] = 'Secure S3 Storageは、成果物のファイル名、サイズ、チェックサム、オブジェクトキー、転送状態、時刻を記録します。コースアーカイブ、DBバックアップ成果物、有効にしたコンテンツオブジェクトには個人データが含まれる場合があり、設定したS3転送先へ送信されます。';
$string['privacy:metadata:s3'] = '設定されたS3互換ストレージへ、有効にした種類のMoodleバックアップ成果物を送信します。';
$string['privacy:metadata:s3:archive'] = 'Moodleのユーザーおよびサイトデータを含む可能性があるコースアーカイブ、DBバックアップ成果物、またはコンテンツオブジェクトです。';
$string['privacy:metadata:transfer'] = '観測および転送したMoodleバックアップ成果物とコンテンツオブジェクトの監査記録です。';
$string['privacy:metadata:transfer:checksum'] = '成果物またはコンテンツオブジェクトのSHA-256チェックサムです。';
$string['privacy:metadata:transfer:filename'] = 'ローカル成果物のファイル名またはコンテンツハッシュです。';
$string['privacy:metadata:transfer:filesize'] = '成果物またはコンテンツオブジェクトのサイズです。';
$string['privacy:metadata:transfer:objectkey'] = '転送先S3オブジェクトキーです。';
$string['privacy:metadata:transfer:status'] = '転送状態です。';
$string['privacy:metadata:transfer:timecreated'] = '成果物またはコンテンツオブジェクトが最初に記録された時刻です。';
$string['region'] = 'AWSリージョン';
$string['region_desc'] = '転送先バケットが存在するAWSリージョンです。';
$string['secure_s3_storage:manage'] = 'Secure S3 Storageの設定とバックアップ転送を管理する';
$string['sourcedirectory'] = 'バックアップディレクトリ';
$string['sourcedirectory_desc'] = 'Moodle自動コースバックアップが使用するサーバー上の絶対パスです。ファイルを調べる前にパスを検証します。';
$string['stabilityseconds'] = '安定性の観測時間';
$string['stabilityseconds_desc'] = 'スケジュールタスクの複数回実行にわたり、ファイルサイズと更新日時が同じ状態を維持する必要がある秒数です。最小値は1秒です。';
$string['task_already_running'] = '別のSecure S3 Storageスキャンが実行中です。';
$string['task_configuration_error'] = 'Secure S3 Storageの設定が無効なため、転送を停止しました。';
$string['task_content_configuration_error'] = 'Secure S3 Storageのコンテンツバックアップ設定が無効なため、転送を停止しました。';
$string['task_content_database_waiting'] = 'コンテンツ回復セット{$a}は、対応するDB成果物のリモート検証完了を待機しています。';
$string['task_content_manifest_rejected'] = '無効なコンテンツ回復マニフェスト{$a}を拒否しました。';
$string['task_content_recovery_set_transferred'] = 'コンテンツ回復セット{$a}を転送し、整合性を検証しました。';
$string['task_content_cycle_complete'] = 'DBと対応するMoodleコンテンツ回復セットを完了し、公開しました。';
$string['task_content_failed'] = 'コンテンツオブジェクト{$a}の転送に失敗しました。ローカルオブジェクトは保持されています。';
$string['task_content_no_files'] = '未完了のコンテンツ回復セットはありません。';
$string['task_content_transferred'] = 'コンテンツオブジェクト{$a}を転送し、整合性を検証しました。';
$string['task_database_configuration_error'] = 'DB成果物の設定が無効なため、Secure S3 Storageは転送を停止しました。';
$string['task_database_created'] = '内蔵DBバックアップ {$a} を生成しました。';
$string['task_database_failed'] = '{$a}のDB成果物転送に失敗しました。ローカルのpayloadとmanifestは保持されています。';
$string['task_database_manifest_rejected'] = '無効なDB成果物manifest {$a}を拒否しました。';
$string['task_database_no_files'] = '転送対象となる生成完了済みDB成果物はありません。';
$string['task_database_production_failed'] = '内蔵DBバックアップの生成に失敗しました。不完全な成果物は公開されていません。';
$string['task_database_transferred'] = 'DB成果物{$a}を転送し、整合性を検証しました。';
$string['task_file_failed'] = '{$a}の転送に失敗しました。ローカルアーカイブは保持されています。';
$string['task_file_observed'] = '{$a}を観測しました。安定性の観測時間が経過するまで待機します。';
$string['task_file_transferred'] = '{$a}を転送し、整合性を検証しました。';
$string['task_no_files'] = '対象となるMoodleバックアップアーカイブはありません。';
$string['task_transfer_content_objects'] = '参照されているMoodleコンテンツオブジェクトをAmazon S3へ転送する';
$string['task_transfer_course_backups'] = '生成済みコースバックアップをAmazon S3へ転送する';
$string['task_transfer_database_backups'] = '生成完了済みDBバックアップ成果物をAmazon S3へ転送する';
$string['transferenabled'] = '定期転送を有効にする';
$string['transferenabled_desc'] = '初期状態では無効です。バックアップ元とS3転送先を検証した後にだけ有効にします。';
