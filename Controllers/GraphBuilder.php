<?php

namespace Rapyd\ERD;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Rapyd\Erd\DocumentorGraph as Graph;

use Illuminate\Support\Collection;
use phpDocumentor\GraphViz\Node;
use \Illuminate\Database\Eloquent\Model as EloquentModel;

class GraphBuilder
{
    /** @var Graph */
    private $graph;

    /**
     * @param $models
     * @return Graph
     */
    public function buildGraph(Collection $models, $type_option, $model_scope) : Graph
    {
        $this->graph = new Graph();

        // CONFIG ATTRIBUES
        foreach (config('erd-generator.graph') as $key => $value) {
            if ($type_option == 'slim') {
                switch ($key) {
                    case 'rankdir': $value = 'LR'; break;
                    case 'splines': $value = 'ortho'; break;
                    case 'nodesep': $value = 2; break;
                }
            } else {
                switch ($key) {
                    case 'rankdir': $value = 'TB'; break;
                    case 'nodesep': $value = 2; break;
                    case 'ranksep': $value = 2; break;
                    case 'esep':    $value = true; break;
                    case 'splines': $value = 'polyline'; break;
                }
            }
            $this->graph->{"set${key}"}($value);
        }

        // Overrides
        if (
            $model_scope == 'bondlibrary' ||
            ($model_scope == 'user' && $type_option != 'slim')
        ) {
            $this->graph->setrankdir('LR');
        }

        $this->addModelsToGraph($models, $type_option, $model_scope);

        return $this->graph;
    }

    protected function getTableColumnsFromModel(EloquentModel $model)
    {
        try {

            $table = $model->getConnection()->getTablePrefix() . $model->getTable();
            $schema = $model->getConnection()->getDoctrineSchemaManager($table);
            $databasePlatform = $schema->getDatabasePlatform();
            $databasePlatform->registerDoctrineTypeMapping('enum', 'string');

            $database = null;

            if (strpos($table, '.')) {
                list($database, $table) = explode('.', $table);
            }

            return $schema->listTableColumns($table, $database);
        } catch (\Throwable $e) {
        }

        return [];
    }

    protected function getModelLabel(EloquentModel $model, string $label, $type_option, $model_scope)
    {
        if ($type_option == 'slim'){
            $table = '<'.$label.PHP_EOL.'>';
        } else {
            // Designate main model source
            if (
                ($label == 'BondPolicies' && $model_scope == 'policies') ||
                ($label == 'BondLibraries' && $model_scope == 'bondlibrary') ||
                ($label == 'User' && $model_scope == 'user')
            ) {
                $header_background_color = '#238F7D';
                $header_font_color = '#FFFFFF';

                $row_background_color = '#DDDDDD';
                $row_font_color = '#000000';

                $row_background_color_2 = '#BBBBBB';
                $row_font_color_2 = '#000000';
            } else {
                $header_background_color = '#666666';
                $header_font_color = '#FFFFFF';
                
                $row_background_color = '#ffffff';
                $row_font_color = '#333333';

                $row_background_color_2 = '#CCCCCC';
                $row_font_color_2 = '#333333';
            }


            $table = '<<table width="100%" height="100%" border="0" margin="0" cellborder="1" cellspacing="0" cellpadding="10">' . PHP_EOL;
            $table .= '<tr width="100%"><td width="100%" bgcolor="'.$header_background_color.'"><font color="'.$header_font_color.'">' . $label . '</font></td></tr>' . PHP_EOL;


            $columns = $this->getTableColumnsFromModel($model);

            $idx_count = 0;
            foreach ($columns as $column) {
                $label = $column->getName();
                if (config('erd-generator.use_column_types')) {
                    $label .= ' ('.$column->getType()->getName().')';
                }

                if ($idx_count % 2 === 0) {
                    $row_bg    = $row_background_color;
                    $row_color = $row_font_color;
                } else {
                    $row_bg    = $row_background_color_2;
                    $row_color = $row_font_color_2;
                }

                $table .= '<tr width="100%"><td port="' . $column->getName() . '" align="left" width="100%"  bgcolor="'.$row_bg.'"><font color="'.$row_color.'" >' . $label . '</font></td></tr>' . PHP_EOL;

                $idx_count++;
            }

            $table .= '</table>>';
        }

        return $table;
    }

    protected function addModelsToGraph(Collection $models, $type_option, $model_scope)
    {
        // Add models to graph
        $models->map(function (Model $model) use ($type_option, $model_scope) {
            $eloquentModel = app($model->getModel());
            $this->addNodeToGraph(
                $eloquentModel, 
                $model->getNodeName(), 
                $model->getLabel(),
                $type_option,
                $model_scope
            );
        });

        // Create relations
        $models->map(function ($model) use ($type_option, $model_scope) {
            $this->addRelationToGraph($model, $type_option, $model_scope);
        });
    }

    protected function addNodeToGraph(
        EloquentModel $eloquentModel, 
        string $nodeName, 
        string $label, 
        $type_option,
        $model_scope
    ) {
        $node = Node::create($nodeName);
        $node->setLabel($this->getModelLabel($eloquentModel, $label, $type_option, $model_scope));

        foreach (config('erd-generator.node') as $key => $value) {
            if ($type_option == 'slim') {
                switch ($key) {
                    case 'margin':      $value = 0; break;
                    case 'shape':       $value = 'rectangle'; break;
                    case 'fontname':    $value = 'Helvetica Neue'; break;
                    case 'height':      $value = 1; break;
                }
            } else {
                switch ($key) {
                    case 'margin':      $value = 0; break;
                    case 'shape':       $value = 'rectangle'; break;
                    case 'fontname':    $value = 'Helvetica Neue'; break;
                    case 'height':      $value = 0.1; break;
                }
            }

            $node->{"set${key}"}($value);
        }

        // Default style settings
        if ($type_option == 'slim') {
            $node->setwidth(2.5);
        }

        // Main model/table styling
        // Source color labeling eval
        if (
            ($label == 'BondPolicies' && $model_scope == 'policies') ||
            ($label == 'BondLibraries' && $model_scope == 'bondlibrary') ||
            ($label == 'User' && $model_scope == 'user')
        ) {
            $node->setstyle('filled');
            $node->setfontcolor('white');
            $node->setfontsize(24);
            $node->setfillcolor('#238F7D'); // Use of magic method  

            if ($type_option == 'slim' && $model_scope == 'policies') {
                $node->setheight(2);
                $node->setwidth(3);
            }            
        }

        $this->graph->setNode($node);
    }

    protected function addRelationToGraph(Model $model, $type_option, $model_scope)
    {

        $modelNode = $this->graph->findNode($model->getNodeName());

        /** @var ModelRelation $relation */
        foreach ($model->getRelations() as $relation) {
            $relatedModelNode = $this->graph->findNode($relation->getModelNodeName());

            if ($relatedModelNode !== null) {
                $this->connectByRelation(
                    $model, 
                    $relation, 
                    $modelNode, 
                    $relatedModelNode, 
                    $type_option, 
                    $model_scope
                );
            }
        }
    }

    /**
     * @param Node $modelNode
     * @param Node $relatedModelNode
     * @param ModelRelation $relation
     */
    protected function connectNodes(
        Node $modelNode, 
        Node $relatedModelNode, 
        ModelRelation $relation,
        $type_option
    ): void
    {
        $edge = Edge::create($modelNode, $relatedModelNode);
        $edge->setFromPort($relation->getLocalKey());
        $edge->setToPort($relation->getForeignKey());
        $edge->setLabel(' ');

        // Set as HTML to give background color to edge label
        if ($type_option == 'slim') {
            $label_html = '<<table border="0" cellborder="0"><tr><td bgcolor="#CCCCCC">'.$relation->getType() . ': ' . $relation->getName().'</td></tr></table>>';

            $edge->setXLabel($label_html);
        }

        foreach (config('erd-generator.edge') as $key => $value) {
            $edge->{"set${key}"}($value);
        }

        foreach (config('erd-generator.relations.' . $relation->getType(), []) as $key => $value) {
            $edge->{"set${key}"}($value);
        }

        $this->graph->link($edge);
    }

    /**
     * @param Model $model
     * @param ModelRelation $relation
     * @param Node $modelNode
     * @param Node $relatedModelNode
     * @return void
     */
    protected function connectBelongsToMany(
        Model $model,
        ModelRelation $relation,
        Node $modelNode,
        Node $relatedModelNode,
        $type_option, 
        $model_scope
    ): void {
        $relationName = $relation->getName();
        $eloquentModel = app($model->getModel());

        /** @var BelongsToMany $eloquentRelation */
        $eloquentRelation = $eloquentModel->$relationName();

        if (!$eloquentRelation instanceof BelongsToMany) {
            return;
        }

        $pivotClass = $eloquentRelation->getPivotClass();

        try {
            /** @var EloquentModel $relationModel */
            $pivotModel = app($pivotClass);
            $pivotModel->setTable($eloquentRelation->getTable());
            $label = (new \ReflectionClass($pivotClass))->getShortName();
            $pivotTable = $eloquentRelation->getTable();
            $this->addNodeToGraph($pivotModel, $pivotTable, $label, $type_option, $model_scope);

            $pivotModelNode = $this->graph->findNode($pivotTable);

            $relation = new ModelRelation(
                $relationName,
                'BelongsToMany',
                $model->getModel(),
                $eloquentRelation->getParent()->getKeyName(),
                $eloquentRelation->getForeignPivotKeyName()
            );

            $this->connectNodes($modelNode, $pivotModelNode, $relation, $type_option);

            $relation = new ModelRelation(
                $relationName,
                'BelongsToMany',
                $model->getModel(),
                $eloquentRelation->getRelatedPivotKeyName(),
                $eloquentRelation->getRelated()->getKeyName()
            );

            $this->connectNodes($pivotModelNode, $relatedModelNode, $relation, $type_option);
        } catch (\ReflectionException $e){}
    }

    /**
     * @param Model $model
     * @param ModelRelation $relation
     * @param Node $modelNode
     * @param Node $relatedModelNode
     */
    protected function connectByRelation(
        Model $model,
        ModelRelation $relation,
        Node $modelNode,
        Node $relatedModelNode,
        $type_option,
        $model_scope
    ): void {

        if ($relation->getType() === 'BelongsToMany') {
            $this->connectBelongsToMany(
                $model, 
                $relation, 
                $modelNode, 
                $relatedModelNode, 
                $type_option,
                $model_scope
            );
            return;
        }

        $this->connectNodes($modelNode, $relatedModelNode, $relation, $type_option);
    }
}