<?php

namespace Aws\Resource;

use GuzzleHttp\HasDataTrait;
use JmesPath as jp;

/**
 * @internal
 */
class Model implements \ArrayAccess, \IteratorAggregate, \Countable
{
    use HasDataTrait;

    private static $paths = [
        'action' => '%s.actions.%s',
        'belongsTo' => '%s.belongsTo.%s.resource',
        'collection' => '%s.hasMany.%s',
        'identifiersList' => '%s.identifiers[].name',
        'load' => '%s.load',
        'subResourcesIds' => '%s.subResources.identifiers',
    ];

    public function __construct($service, array $data)
    {
        if (!isset($data['service']) || !isset($data['resources'])) {
            throw new \InvalidArgumentException('Resource models must contain '
                . 'the keys "service" and "resources" at the top level.');
        }

        // Create a service-level subResources key.
        $topLevelResources = array_values(array_diff(
            jp\search('keys(resources)', $data),
            jp\search('resources.*.subResources.resources[]', $data)
        ));
        $data['service']['subResources'] = [
            'resources' => $topLevelResources,
            'identifiers' => []
        ];

        $data['service']['_meta'] = $this->createMeta($data['service'], $service);
        foreach ($data['resources'] as $type => $info) {
            $data['resources'][$type]['_meta'] = $this->createMeta($info, $service);
        }

        $this->data = $data;
    }

    public function search($expr, $resource = null, $action = null)
    {
        if ($resource || $action) {
            if (!isset(self::$paths[$expr])) {
                throw new \InvalidArgumentException(
                    "Named expression, {$resource}, does not exist."
                );
            }

            if ($resource !== 'service') {
                $resource = 'resources.' . $resource;
            }

            $expr = sprintf(self::$paths[$expr], $resource, $action);
        }

        return jp\search($expr, $this->data);
    }

    private function createMeta(array $data, $service)
    {
        $meta = [
            'actions'      => jp\search('keys(actions||`[]`)', $data),
            'belongsTo'    => jp\search('keys(belongsTo||`[]`)', $data),
            'collections'  => jp\search('keys(hasMany||`[]`)', $data),
            'subResources' => jp\search('subResources.resources', $data) ?: [],
        ];

        $methods = [];
        foreach ($meta as $key => $items) {
            foreach ($items as $item) {
                $methods[lcfirst($item)] = $key;
            }
        }

        $meta['methods'] = $methods;
        $meta['serviceName'] = $service;
        $meta['identifiers'] = jp\search('identifiers[].name', $data) ?: [];

        return $meta;
    }
}