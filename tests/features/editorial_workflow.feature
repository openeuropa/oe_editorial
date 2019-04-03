@api
Feature: Corporate editorial workflow
  As a content editor
  I can moderate content through different states by different roles

  Scenario: As an Author user, I can create a Draft demo content and I can send it for Needs Review
    Given I am logged in as a user with the "Author" role
    When I visit "the demo content creation page"
    Then I should have the following options for the "Save as" select:
      | Draft |
    When I fill in "Title" with "Workflow demo"
    And I press "Save"
    And I visit "the content administration page"
    # The content is created and it is not published.
    Then I should see the text "not published" in the "Workflow demo" row
    When I click "Edit"
    Then the current workflow state should be "Draft"
    # Check that the only available next state is Needs Review.
    When I click "View"
    Then I should have the following options for the "Change to" select:
      | Needs Review |
    # After setting it to Needs Review the Author can't edit the node anymore.
    When I select "Needs Review" from "Change to"
    And I press "Apply"
    Then I should not see the link "Edit"

  Scenario: As a Reviewer user, I can edit demo content in Needs Review state and
  I can send it back to Draft or to Request Validation
    Given I am logged in as a user with the "Reviewer" role
    And "oe_workflow_demo" content:
      | title         | moderation_state |
      | Workflow node | needs_review     |
    And I visit "the content administration page"
    And I click "Workflow node"
    # We are on the node edit page and we see the current state is Needs Review and we can only save as Draft.
    When I click "Edit"
    Then I should see text matching "Edit Demo Workflow node"
    And the current workflow state should be "Needs Review"
    And I should have the following options for the "Change to" select:
      | Draft |
    When I click "View"
    Then I should have the following options for the "Change to" select:
      | Draft              |
      | Request Validation |
    # After Request Validation I have no Edit access.
    When I select "Request Validation" from "Change to"
    And I press "Apply"
    Then I should not see the link "Edit"

  Scenario: As a Validator user, I can edit demo content in Request Validation state and
  I can validate the node and publish.
    Given I am logged in as a user with the "Validator" role
    And "oe_workflow_demo" content:
      | title         | moderation_state   |
      | Workflow node | request_validation |
    And I visit "the content administration page"
    And I click "Workflow node"
    When I click "Edit"
    # We are on the node edit page and we see the current state is Request Validation and we can only save as Draft.
    Then I should see text matching "Edit Demo Workflow node"
    And the current workflow state should be "Request Validation"
    And I should have the following options for the "Change to" select:
      | Draft |
    # After validation I have Edit access and I can publish the node.
    When I click "View"
    And I should have the following options for the "Change to" select:
      | Draft        |
      | Needs Review |
      | Validated    |
    When I select "Validated" from "Change to"
    And I press "Apply"
    And I click "Edit"
    Then I should have the following options for the "Change to" select:
      | Draft |
    When I click "View"
    Then I should have the following options for the "Change to" select:
      | Draft     |
      | Published |
    When I select "Published" from "Change to"
    And I press "Apply"
    And I click "New draft"
    Then the current workflow state should be "Published"

  Scenario: As an Author user, I can Publish a Validated demo content.
    Given users:
      | name        | roles  |
      | author_user | Author |
    And "oe_workflow_demo" content:
      | title         | moderation_state | author      |
      | Workflow node | validated        | author_user |
    And I am logged in as "author_user"
    When I visit "the content administration page"
    # The content is created and it is not published.
    Then I should see the text "not published" in the "Workflow node" row
    When I click "Workflow node"
    And I click "Edit"
    # After validation I have Edit access and I can publish the node.
    Then I should have the following options for the "Change to" select:
      | Draft |
    When I click "View"
    Then I should have the following options for the "Change to" select:
      | Draft     |
      | Published |
    When I select "Published" from "Change to"
    And I press "Apply"
    And I click "New draft"
    # After Publish I can restart the workflow.
    Then I should have the following options for the "Change to" select:
      | Draft |
    Then the current workflow state should be "Published"
    When I visit "the content administration page"
    # The content is created and it is published.
    Then I should see text matching "published"
    And I should not see text matching "not published"

  Scenario: As an Author user, I can create new draft for published content.
    Given users:
      | name        | roles  |
      | author_user | Author |
    And "oe_workflow_demo" content:
      | title         | moderation_state | author      |
      | Workflow node | published        | author_user |
    And I am logged in as "author_user"
    When I visit "the content administration page"
    # The content is created and it is published.
    Then I should see the text "published" in the "Workflow node" row
    When I click "Workflow node"
    And I click "New draft"
     # After Publish I can restart the workflow.
    Then I should have the following options for the "Change to" select:
      | Draft |
    When I select "Draft" from "Change to"
    And I press "Save"
    And I should see "Edit draft"
    When I visit "the content administration page"
    # The content is created and it is published.
    Then I should see text matching "published"
    And I should not see text matching "not published"

  Scenario: As an Author user, I can see the node revisions.
    Given I am logged in as a user with the "Author" role
    And I visit "the demo content creation page"
    And I fill in "Title" with "Workflow demo"
    And I press "Save"
    When I select "Needs Review" from "Change to"
    And I press "Apply"
    And I click Revisions
    Then I should see "Revisions for Workflow demo"

  Scenario: As a user with combined roles I can publish a node and I can revert revisions.
    Given I am logged in as a user with the "Author, Reviewer, Validator" roles
    And I visit "the demo content creation page"
    And I fill in "Title" with "Workflow demo"
    And I press "Save"
    And I select "Needs Review" from "Change to"
    And I press "Apply"
    And I select "Request Validation" from "Change to"
    And I press "Apply"
    And I select "Validated" from "Change to"
    And I press "Apply"
    # Node is in published state.
    And I select "Published" from "Change to"
    And I press "Apply"
    When I click Revisions
    And I click Revert
    # Confirmation page.
    Then I should see "Are you sure you want to revert to the revision"
