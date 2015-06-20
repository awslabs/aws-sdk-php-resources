<?php
namespace Aws\Resource;

use Aws\AwsClientInterface;
use Aws\Command;
use Aws\Result;
use Aws\ResultInterface;
use JmesPath as jp;

/**
 * @internal
 */
class ResourceClient
{
    /** @var AwsClientInterface */
    private $apiClient;

    /** @var Model */
    private $model;

    public function __construct(AwsClientInterface $apiClient, Model $model)
    {
        $this->apiClient = $apiClient;
        $this->model = $model;
    }

    public function getApiClient()
    {
        return $this->apiClient;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getMetaData($type)
    {
        $type = ($type !== 'service') ? 'resources.' . $type : $type;

        return $this->model->search("{$type}._meta");
    }

    public function loadResourceData(Resource $resource)
    {
        if ($load = $this->model->search('load', $resource->getType())) {
            $command = $this->prepareCommand($load['request'], $resource);
            $result = $this->apiClient->execute($command);
            $expr = ($load['path'] === '$') ? '@' : $load['path'];

            return $result->search($expr) ?: [];
        }

        return [];
    }

    public function makeSubResource($name, array $args, Resource $parent)
    {
        return new Resource($this, $name, $this->createIdentityForSubResource(
            $parent->getIdentity(),
            $this->model->search('identifiersList', $name) ?: [],
            $args,
            $this->model->search('subResourcesIds', $parent->getType()) ?: []
        ));
    }

    /**
     * @param string   $name
     * @param array    $args
     * @param Resource $parent
     *
     * @return Resource|Batch
     */
    public function makeBelongsToResource($name, array $args, Resource $parent)
    {
        $resource = $this->model->search('belongsTo', $parent->getType(), $name);

        $id = $this->createIdentityForRelatedResource(
            $resource['identifiers'],
            function (array $param) use ($parent) {
                return $this->resolveValue($param, $parent);
            }
        );

        return $this->createResources($resource, $parent, $id);
    }

    /**
     * @param string   $name
     * @param array    $args
     * @param Resource $resource
     *
     * @return ResultInterface|Batch|Resource
     */
    public function performAction($name, array $args, Resource $resource)
    {
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }

        $action = $this->model->search('action', $resource->getType(), $name);

        $command = $this->prepareCommand($action['request'], $resource, $args);
        $result = $this->apiClient->execute($command);

        if (isset($action['resource'])) {
            $id = $this->createIdentityForRelatedResource(
                $action['resource']['identifiers'],
                function (array $param) use ($resource, $command, $result) {
                    return $this->resolveValue($param, $resource, $command, $result);
                }
            );

            return $this->createResources($action['resource'], $result, $id);
        } else {
            return $result;
        }
    }

    public function makeCollection($name, array $args, Resource $parent)
    {
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }

        // Get the information on how to process the collection.
        $info = $this->model->search('collection', $parent->getType(), $name);

        // Create a paginator or an iterator that yields a single command's result.
        $command = $this->prepareCommand($info['request'], $parent, $args);
        if ($this->apiClient->getApi()->hasPaginator($command->getName())) {
            $paginator = $this->apiClient->getPaginator(
                $command->getName(),
                $command->toArray()
            );
        } else {
            $paginator = \Aws\map([$command], function (Command $command) {
                return $this->apiClient->execute($command);
            });
        }

        // Create a new from the paginator, including a lambda that coverts
        // results to batches by using info from the resources model.
        return new Collection(
            $this,
            $parent->getType(),
            $paginator,
            function (Result $result) use ($info, $parent, $command) {
                $ids = $this->createIdentityForRelatedResource(
                    $info['resource']['identifiers'],
                    function (array $param) use ($parent, $command, $result) {
                        return $this->resolveValue($param, $parent, $command, $result);
                    }
                );

                return ($ids === null)
                    ? new Batch($this, $info['resource']['type'], [])
                    : $this->createResources($info['resource'], $result, $ids);
            }
        );
    }

    public function waitUntil($name, array $args, Resource $resource)
    {
        $config = isset($args[0]) ? $args[0] : [];
        $args = [];

        $waiter = $this->model->search('waiter', $resource->getType(), $name);
        $this->prepareArgs($waiter['params'], $resource, $args);
        $this->apiClient->waitUntil($waiter['waiterName'], $args, $config);

        return $resource;
    }

    /**
     * @param array    $request
     * @param Resource $resource
     * @param array    $args
     *
     * @return Command
     */
    private function prepareCommand(
        array $request,
        Resource $resource,
        array $args = []
    ) {
        if (isset($request['params'])) {
            $this->prepareArgs($request['params'], $resource, $args);
        }

        return $this->apiClient->getCommand($request['operation'], $args);
    }

    /**
     * @param array    $params
     * @param Resource $resource
     * @param array    $args
     */
    private function prepareArgs(
        array $params,
        Resource $resource,
        array &$args
    ) {
        // Star is used track the index for targets with "[*]".
        $star = null;

        // Resolve and set the arguments for the operation.
        foreach ($params as $param) {
            $value = $this->resolveValue($param, $resource);
            $this->setArgValue($param['target'], $value, $args, $star);
        }
    }

    /**
     * @param array                    $info
     * @param ResultInterface|Resource $data
     * @param array                    $identity
     *
     * @return Resource|Batch
     */
    private function createResources(array $info, $data, array $identity)
    {
        $data = isset($info['path'])
            ? jp\search($info['path'], $data)
            : null;

        if (isset($identity[0])) {
            $resources = [];
            foreach ($identity as $index => $id) {
                $datum = isset($data[$index]) ? $data[$index] : $data;
                $resources[] = new Resource($this, $info['type'], $id, $datum);
            }
            return new Batch($this, $info['type'], $resources);
        } else {
            return new Resource($this, $info['type'], $identity, $data);
        }
    }

    private function resolveValue(
        array $param,
        Resource $resource,
        Command $command = null,
        ResultInterface $result = null
    ) {
        switch ($param['sourceType']) {
            // Source is pulled from the resource's identifier.
            case 'identifier':
                $id = $resource->getIdentity();
                return isset($id[$param['source']])
                    ? $id[$param['source']]
                    : null;
            // Source is pulled from the resource's data.
            case 'dataMember':
                return jp\search($param['source'], $resource);
            // Source is pulled from the command parameters.
            case 'requestParameter':
                return $command[$param['source']];
            // Source is pulled from the result.
            case 'responsePath':
                return $result ? $result->search($param['source']) : null;
            // Source is a literal value from the resource model.
            case 'string':
            case 'integer':
            case 'boolean':
                return $param['source'];
            // Invalid source type.
            default:
                throw new \InvalidArgumentException('The value "'
                    . $param['sourceType'] . '" is an invalid for sourceType.');
        }
    }

    private function setArgValue($target, $value, array &$args, &$star)
    {
        // Split up the target into tokens for evaluation.
        if (!preg_match_all('/\w+|\.|\[\]|\[[0-9*]+\]/', $target, $tokens)) {
            throw new \UnexpectedValueException('Invalid target expression.');
        }

        // Initialize the cursor at the args array root.
        $cursor = &$args;

        // Create/traverse an args array structure based on the tokens.
        foreach ($tokens[0] as $token) {
            $trimmedToken = trim($token, '[]');
            if ($token === '.') {
                // Handle hash context.
                if (!is_array($cursor)) {
                    $cursor = [];
                }
                continue;
            } elseif ($token === '[]') {
                // Handle list context.
                $index = count($cursor);
            } elseif ($trimmedToken === '*') {
                // Handle list context with pairing.
                if ($star === null) {
                    $star = count($cursor);
                }
                $index = $star;
            } elseif (is_numeric($trimmedToken)) {
                // Handle list context with specific index.
                $index = $trimmedToken;
            } else {
                // Handle identifier context.
                $index = $token;
            }

            // Make sure the index exists.
            if (!isset($cursor[$index])) {
                $cursor[$index] = null;
            }

            // Move the cursor.
            $cursor =& $cursor[$index];
        }

        // Finally, set the value.
        $cursor = $value;
    }

    private function createIdentityForSubResource(
        array $parentId,
        array $identifiers,
        array $newParts = [],
        array $renames = []
    ) {
        if (count($identifiers) !== (count($parentId) + count($newParts))) {
            throw new \InvalidArgumentException('Invalid identity.');
        }

        $id = [];

        // Get the identity parts from the parent and apply any renames.
        foreach ($parentId as $key => $value) {
            $id[isset($renames[$key]) ? $renames[$key] : $key] = $value;
        }

        // Fill in remaining identity parts from the provided new parts.
        foreach ($identifiers as $identifier) {
            if (!isset($id[$identifier])) {
                $id[$identifier] = array_shift($newParts);
            }
        }

        return $id;
    }

    public function createIdentityForRelatedResource(
        array $identifiers,
        callable $resolve
    ) {
        $data = [];
        $plurals = [];
        foreach ($identifiers as $info) {
            $data[$info['target']] = $resolve($info);
            if (is_null($data[$info['target']])) {
                return null;
            } elseif (is_array($data[$info['target']])) {
                $plurals[$info['target']] = count($data[$info['target']]);
            } else {
                $plurals[$info['target']] = 0;
            }
        }

        if (($numIds = max($plurals)) > 0) {
            $ids = [];
            for ($i = 0; $i < $numIds; $i++) {
                $id = [];
                foreach ($data as $key => $value) {
                    $id[$key] = is_array($value) ? $value[$i] : $value;
                }
                $ids[] = $id;
            }

            return $ids;
        }

        return $data;
    }
}
