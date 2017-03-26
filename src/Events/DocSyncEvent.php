<?php

namespace Lingxi\AliOpenSearch\Events;

class DocSyncEvent
{
	public $indexName;

	public $tableName;

	public $data;

	public $type;

	public $success;

	public $message;

	public function __construct($indexName, $tableName, array $data, $type, $success = true, $message = '')
	{
		$this->data = $data;
		$this->type = $type;
		$this->success = $success;
		$this->message = $message;
		$this->indexName = $indexName;
		$this->tableName = $tableName;
	}
}
