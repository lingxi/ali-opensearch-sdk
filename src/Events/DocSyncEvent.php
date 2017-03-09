<?php

namespace Lingxi\AliOpenSearch\Doc\Events;

class DocSyncEvent
{
	public $indexName;

	public $tableName;

	public $data;

	public $type;

	public $success;

	public function __construct($indexName, $tableName, $data, $type, $success = true)
	{
		$this->data = $data;
		$this->type = $type;
		$this->success = $success;
		$this->indexName = $indexName;
		$this->tableName = $tableName;
	}
}
