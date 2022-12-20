Feature: mountpoint

  Scenario: Set edit and share permissions for collective and check mountpoint permissions
    When user "jane" creates collective "BehatMountPoint"
    And user "jane" sets "edit" level in collective "BehatMountPoint" to "Admin"
    And user "jane" sets "share" level in collective "BehatMountPoint" to "Moderator"
    And user "john" joins circle "BehatMountPoint" with owner "jane" with level "Admin"
    And user "alice" joins circle "BehatMountPoint" with owner "jane" with level "Moderator"
    And user "bob" joins circle "BehatMountPoint" with owner "jane"
    Then user "jane" has webdav access to "BehatMountPoint" with permissions "RMGDNVCK"
    And user "john" has webdav access to "BehatMountPoint" with permissions "RMGDNVCK"
    And user "alice" has webdav access to "BehatMountPoint" with permissions "RMG"
    And user "bob" has webdav access to "BehatMountPoint" with permissions "MG"

  Scenario: Trash and delete collective and circle
    Then user "jane" trashes collective "BehatMountPoint"
    And user "jane" deletes collective+circle "BehatMountPoint"
