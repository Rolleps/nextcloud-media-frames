<?php

declare(strict_types=1);

namespace OCA\MediaFrames\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20260510000000 extends SimpleMigrationStep
{
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
  {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('media_frames_frames')) {
      $table = $schema->createTable('media_frames_frames');

      $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 4]);
      $table->addColumn('user_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
      $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 100]);
      $table->addColumn('share_token', Types::STRING, ['notnull' => true, 'length' => 64]);

      // JSON array of source objects: [{type:'album',albumId:1},{type:'folder',path:'/Photos',recursive:true}]
      $table->addColumn('sources', Types::TEXT, ['notnull' => true, 'default' => '[]']);

      $table->addColumn('selection_method', Types::STRING, ['notnull' => true, 'length' => 50]);
      $table->addColumn('favor_new_additions', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
      $table->addColumn('rotation_unit', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'hour']);
      $table->addColumn('rotations_per_unit', Types::INTEGER, ['notnull' => true, 'default' => 1]);
      $table->addColumn('start_day_at', Types::TIME, ['notnull' => true]);
      $table->addColumn('end_day_at', Types::TIME, ['notnull' => true]);

      // Duration settings
      $table->addColumn('image_duration_seconds', Types::INTEGER, ['notnull' => true, 'default' => 30]);
      $table->addColumn('video_duration', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'full']);
      $table->addColumn('video_fixed_duration_seconds', Types::INTEGER, ['notnull' => true, 'default' => 30]);

      // Device access protection (bcrypt hash; null = no protection)
      $table->addColumn('device_password_hash', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);

      // Display options
      $table->addColumn('show_photo_timestamp', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
      $table->addColumn('show_photo_place', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
      $table->addColumn('show_clock', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
      $table->addColumn('photo_size', Types::STRING, ['notnull' => true, 'length' => 50, 'default' => 'smart-fit']);
      $table->addColumn('background_type', Types::STRING, ['notnull' => true, 'length' => 50, 'default' => 'aura']);
      $table->addColumn('background_color', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => '#000000']);
      $table->addColumn('javascript', Types::TEXT, ['notnull' => false, 'default' => '']);

      $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);

      $table->setPrimaryKey(['id']);
      $table->addIndex(['user_uid'], 'mf_frames_user_uid');
      $table->addUniqueIndex(['share_token'], 'mf_frames_share_token');
    }

    if (!$schema->hasTable('media_frames_entries')) {
      $table = $schema->createTable('media_frames_entries');

      $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 4]);
      $table->addColumn('file_id', Types::BIGINT, ['notnull' => true, 'length' => 4]);
      $table->addColumn('frame_id', Types::BIGINT, ['notnull' => true, 'length' => 4]);
      $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);

      $table->setPrimaryKey(['id']);
      $table->addIndex(['frame_id'], 'mf_entries_frame_id');
      $table->addIndex(['created_at'], 'mf_entries_created_at');
    }

    return $schema;
  }
}
