<?php
namespace Leadsapi\Gate;

class BulkResult extends Result
{
    public $enqueued = 0;
    public $errors = [];
}
