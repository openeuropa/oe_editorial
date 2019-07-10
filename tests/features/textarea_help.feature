@ap
Feature: Textarea help
  As a content editor
  When I edit content
  The textarea help should take me to the relevant help page

  Scenario: Textarea help links point to the proper help page.
    Given I am logged in as a user with the "Author" role
    When I visit "the demo content creation page"
    And I should see the link "About text formats" point to "the plain text help page"