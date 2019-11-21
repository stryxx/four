Feature: Get content fields with API

  @api
  Scenario: As a user I fetch fields of content
    When I send a GET request to "/api/contents/1/fields.json"
    Then the response status code should be 200
    And the response should be in JSON
    And the response should contain json:
  """
  "@array@.repeat({\"name\": \"@string@\", \"type\": \"@string@\", \"value\": \"@array@\"})"
  """
  @api
  Scenario: As a user I fetch fields of content in JSON+LD format
    When I send a GET request to "/api/contents/1/fields.jsonld"
    Then the response status code should be 200
    And the response should be in JSON
    And the response should contain json:
  """
  {
     "@context": "/api/contexts/Field",
     "@id": "/api/contents/1/fields",
     "@type": "hydra:Collection",
     "hydra:member": "@array@.repeat({\"@id\": \"@string@\", \"@type\": \"@string@\", \"name\": \"@string@\", \"type\": \"@string@\", \"value\": \"@array@\"})",
     "hydra:totalItems": @integer@
  }
  """
