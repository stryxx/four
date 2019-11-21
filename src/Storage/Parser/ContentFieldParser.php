<?php

declare(strict_types=1);

namespace Bolt\Storage\Query\Parser;

use Bolt\Collection\DeepCollection;
use Bolt\Configuration\Config;
use Bolt\Storage\Query\Conditional\Types;
use Bolt\Storage\Query\Definition\ContentFieldsDefinition;
use Bolt\Storage\Query\Types\ImageType;
use Bolt\Storage\Query\Types\RepeaterType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use Ramsey\Uuid\Uuid;

class ContentFieldParser
{
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getParsedContentFields(): array
    {
        $contentTypes = $this->config->get('contenttypes');
        $contentTypesFields = [];
        $contentTypesFields['content'] = [];
        /** @var DeepCollection $contentTypeConfiguration */
        foreach ($contentTypes as $contentType => $contentTypeConfiguration) {
            $fields = $this->parseContentTypeFields(
                $contentType,
                $contentTypeConfiguration->get('fields')
            );
            $contentTypesFields[$contentType] = $fields;
            $contentTypesFields['content'] = array_merge($contentTypesFields['content'], $fields);
        }

        return $contentTypesFields;
    }

    public function parseContentTypeFields(string $contentType, DeepCollection $fields): array
    {
        $parsedFields = [];
        foreach ($fields as $fieldName => $fieldConfiguration) {
            $parsedFields[$fieldName] = $this->getTypeForField($contentType, $fieldConfiguration);
        }

        $parsedFields = array_merge($parsedFields, ContentFieldsDefinition::getMainContentFields());

        return $this->prepareConditionalFields($parsedFields);
    }

    public function getContentFilterFields(string $contentType, bool $isFirst = true): array
    {
        $contentFields = $this->getParsedContentFields();
        $filterFields = [
            'OR' => [
                'type' => new ListOfType(Type::nonNull(
                    new InputObjectType([
                        'name' => 'OR_filter_'.Uuid::uuid4()->toString(),
                        'fields' => $isFirst ? array_merge(
                            $contentFields[$contentType],
                            $this->getContentFilterFields($contentType, false)
                        ) : $contentFields[$contentType],
                    ])
                )),
            ],
            'AND' => [
                'type' => new ListOfType(Type::nonNull(
                    new InputObjectType([
                        'name' => 'AND_filter_'.Uuid::uuid4()->toString(),
                        'fields' => $isFirst ? array_merge(
                            $contentFields[$contentType],
                            $this->getContentFilterFields($contentType, false)
                        ) : $contentFields[$contentType],
                    ])
                )),
            ],
        ];

        return array_merge($filterFields, $contentFields[$contentType]);
    }

    private function getTypeForField(string $contentType, DeepCollection $fieldConfiguration): Type
    {
        switch ($fieldConfiguration['type']) {
            case 'text':
            case 'slug':
            case 'textarea':
            case 'html':
            case 'templateselect':
            case 'file':
            case 'video':
            case 'filelist':
            case 'imagelist':
            case 'embed':
            case 'geolocation':
            case 'markdown':
            case 'date':
                return Type::string();
                break;
            case 'checkbox':
                return Type::int();
                break;
            case 'number':
                if ($fieldConfiguration['mode'] === 'integer') {
                    return Type::int();
                }
                return Type::float();

                break;
            case 'select':
                return Type::listOf(Type::string());
                break;
            case 'block':
            case 'repeater':
                return Type::listOf(
                    new RepeaterType(
                        $this->parseContentTypeFields($contentType, $fieldConfiguration['fields'])
                    )
                );
                break;
            case 'image':
                return new ImageType();
                break;
        }

        return Type::string();
    }

    private function prepareConditionalFields(array $contentFields): array
    {
        $conditionalFields = [];
        foreach ($contentFields as $field => $type) {
            switch (true) {
                case $type instanceof StringType:
                    $conditionalFields += [
                        $field.Types::CONTAINS => $type,
                        $field.Types::NOT_CONTAINS => $type,
                    ];
                    break;
                case $type instanceof IntType:
                case $type instanceof IDType:
                    $conditionalFields += [
                        $field.Types::LESS_THAN => $type,
                        $field.Types::LESS_THAN_EQUAL => $type,
                        $field.Types::GREATER_THAN => $type,
                        $field.Types::GREATER_THAN_EQUAL => $type,
                    ];
                    break;
                case $type instanceof \DateTime:
                    $conditionalFields += [
                        $field.Types::CONTAINS => $type,
                        $field.Types::NOT_CONTAINS => $type,
                        $field.Types::LESS_THAN => $type,
                        $field.Types::LESS_THAN_EQUAL => $type,
                        $field.Types::GREATER_THAN => $type,
                        $field.Types::GREATER_THAN_EQUAL => $type,
                    ];
                    break;
            }
            $conditionalFields += [
                $field.Types::IN => Type::listOf($type),
                $field.Types::NOT_IN => Type::listOf($type),
                $field.Types::NOT => $type,
            ];
        }

        return $contentFields + $conditionalFields;
    }
}
