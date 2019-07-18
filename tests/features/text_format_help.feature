@api
Feature: Text format help
  As a content editor
  When I edit content
  The text format help link should take me to the relevant text format help page

  @javascript @cleanup-formats
  Scenario: Text format help links point to the proper help page.
    Given I am logged in as a user with the "Author" role
    And the following text formats are available:
      | id               | title              |
      | rich_text        | Rich text          |
      | simple_rich_text | Simple rich text   |
    When I visit "the demo content creation page"
    Then I should see "Rich Text"
    And I should see the link "About the Rich text format" point to "the rich text help page"
    And I should not see "About the Simple rich text format"
    When I select "Simple rich" from "Text format"
    And I should see the link "About the Simple rich text format" point to "the simple rich text help page"
    And I should not see "About the Rich text format"
