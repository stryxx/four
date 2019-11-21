Feature: Preview record after editing

  @javascript
  Scenario: As an Admin I want to preview an edited record
    Given I am logged in as "admin"
    When I am on "/bolt/edit/30"
    When I fill in "field-title" with "Check preview"
    And I scroll ".btn-primary" into view
    And I press "Preview"
    And I switch to tab "1"
    Then I should see "Check preview" in the ".title" element