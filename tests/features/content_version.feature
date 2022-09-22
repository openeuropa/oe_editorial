@api
Feature: Content version
  As a content editor
  I can moderate content and its version will change according to the corporate rules.
  
  Scenario: The version field value changes based on the configured transitions.
    Given users:
      | name        | roles                                          |
      | author_user | Author, Reviewer, Validator |
    And "oe_workflow_demo" content:
      # When the node is created, it already gets the version 0.1.0 as default.
      | title         | moderation_state | author      |
      | Workflow node | draft            | author_user |
    And I am logged in as "author_user"
    When I visit "the content administration page"
    # Create a new Draft revision without changing the content.
    And I click "Workflow node"
    And I click "Edit draft"
    And I press "Save"
    Then the node "Workflow node" should have the following version:
      | major | 0 |
      | minor | 1 |
      | patch | 0 |
    # Create a new Draft revision by changing the content.
    When I click "Edit draft"
    And I fill in "Title" with "Workflow node 1"
    And I press "Save"
    Then the node "Workflow node 1" should have the following version:
      | major | 0 |
      | minor | 2 |
      | patch | 0 |
    # Set to Needs Review and move back to Draft without changes.
    When I select "Needs Review" from "Change to"
    And I press "Apply"
    Then the node "Workflow node 1" should have the following version:
      | major | 0 |
      | minor | 2 |
      | patch | 0 |
    When I select "Draft" from "Change to"
    And I press "Apply"
    Then the node "Workflow node 1" should have the following version:
      | major | 0 |
      | minor | 2 |
      | patch | 0 |
    # Set to Request Review and move back to Draft by changing the content.
    When I select "Request Validation" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    Then the node "Workflow node 1" should have the following version:
      | major | 0 |
      | minor | 2 |
      | patch | 0 |
    When I click "Edit draft"
    And I fill in "Title" with "Workflow node 2"
    And I press "Save"
    Then the node "Workflow node 2" should have the following version:
      | major | 0 |
      | minor | 3 |
      | patch | 0 |
    # Set to validated.
    When I select "Validated" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    Then the node "Workflow node 2" should have the following version:
      | major | 1 |
      | minor | 0 |
      | patch | 0 |
    # Set to draft by making a change.
    When I click "Edit draft"
    And I fill in "Title" with "Workflow node 3"
    And I press "Save"
    Then the node "Workflow node 3" should have the following version:
      | major | 1 |
      | minor | 1 |
      | patch | 0 |
    # Set back to validated.
    When I select "Validated" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    Then the node "Workflow node 3" should have the following version:
      | major | 2 |
      | minor | 0 |
      | patch | 0 |
    # Set to draft by not making a change. It should increase the minor even
    # if we didn't make any change
    When I click "Edit draft"
    And I press "Save"
    Then the node "Workflow node 3" should have the following version:
      | major | 2 |
      | minor | 1 |
      | patch | 0 |
    # Set back to validated.
    When I select "Validated" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    Then the node "Workflow node 3" should have the following version:
      | major | 3 |
      | minor | 0 |
      | patch | 0 |
    # Set to Published and back to Draft.
    When I select "Published" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    And I click "New draft"
    And I press "Save"
    Then the node "Workflow node 3" should have the following version:
      | major | 3 |
      | minor | 1 |
      | patch | 0 |
    # Set to published.
    When I select "Published" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    Then the node "Workflow node 3" should have the following version:
      | major | 4 |
      | minor | 0 |
      | patch | 0 |
    # Unpublish the node by archiving it.
    When I click "Unpublish"
    And I select "Archived" from "Select the unpublishing state"
    And I press "Unpublish"
    Then the node "Workflow node 3" should have the following version:
      | major | 4 |
      | minor | 1 |
      | patch | 0 |
    # Publish again.
    When I select "Published" from "Change to"
    And I press "Apply"
    And I wait for the batch to complete
    Then the node "Workflow node 3" should have the following version:
      | major | 5 |
      | minor | 0 |
      | patch | 0 |
    # Unpublish using the expired state.
    When I click "Unpublish"
    And I select "Expired" from "Select the unpublishing state"
    And I press "Unpublish"
    Then the node "Workflow node 3" should have the following version:
      | major | 5 |
      | minor | 1 |
      | patch | 0 |
