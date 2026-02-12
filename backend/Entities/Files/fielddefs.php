<?php

return array (
  'fields' => 
  array (
    'id' => 
    array (
      'type' => 'uuid',
    ),
    'name' => 
    array (
      'type' => 'string',
    ),
    'path' => 
    array (
      'type' => 'string',
    ),
    'size' => 
    array (
      'type' => 'integer',
    ),
    'mime_type' => 
    array (
      'type' => 'string',
    ),
    'extension' => 
    array (
      'type' => 'string',
    ),
    'owner' => 
    array (
      'type' => 'relationship',
      'entity' => 'users',
    ),
    'date_created' => 
    array (
      'type' => 'datetime',
      'readonly' => true,
    ),
    'date_modified' => 
    array (
      'type' => 'datetime',
      'readonly' => true,
    ),
  ),
);
