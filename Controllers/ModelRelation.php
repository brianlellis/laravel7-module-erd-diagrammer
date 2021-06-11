<?php

namespace Rapyd\ERD;
use Illuminate\Support\Str;

class ModelRelation
{
  private $type;
  private $model;
  private $localKey;
  private $foreignKey;
  private $name;

  public function __construct($name, $type, $model, $localKey = null, $foreignKey = null)
  {
    $this->type       = $type;
    $this->model      = $model;
    $this->localKey   = $localKey;
    $this->foreignKey = $foreignKey;
    $this->name       = $name;
  }

  public function getModel()
  {
    return $this->model;
  }

  public function getModelNodeName()
  {
    return Str::slug($this->model);
  }

  public function getType()
  {
    return $this->type;
  }

  public function getLocalKey()
  {
    return $this->localKey;
  }

  public function getForeignKey()
  {
    return $this->foreignKey;
  }

  public function getName()
  {
    return $this->name;
  }
}
