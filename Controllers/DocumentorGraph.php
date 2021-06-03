<?php

namespace Rapyd\Erd;

use phpDocumentor\GraphViz\Graph;

class DocumentorGraph extends Graph
{
    // Exports this graph to a generated image.
    public function export($type, $filename, $engine = 'dot')
    {
        $type = escapeshellarg($type);
        $filename = escapeshellarg($filename);

        // write the dot file to a temporary file
        $tmpfile = tempnam(sys_get_temp_dir(), 'gvz');

        file_put_contents($tmpfile, (string)$this);

        // escape the temp file for use as argument
        $tmpfileArg = escapeshellarg($tmpfile);

        // create the dot output
        $output = array();
        $code = 0;
        exec($this->path . "{$engine} -T$type -o$filename < $tmpfileArg 2>&1", $output, $code);
        unlink($tmpfile);

        if ($code != 0) {
            throw new Exception(
                'An error occurred while creating the graph; GraphViz returned: '
                . implode(PHP_EOL, $output)
            );
        }

        return $this;
    }
}