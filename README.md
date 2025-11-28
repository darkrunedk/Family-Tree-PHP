# Family Tree (PHP)

This is a helper class I made to make it easier to make family trees in SVG using PHP.

The way it works is you make a new instance of the FamilyTree class.
After thay you add the members to the canvas (preferably starting from the top and working your way downwards). Each members has an id, that you will use to create the relations between the members.

All you need from this project to get started is the FamilyTree.php class found inside the classes folder.

## Example
```PHP
<?php

$tree = new FamilyTree();
$tree->addMember(1, "Grandfather A");
$tree->addMember(2, "Grandmother A");
$tree->addRelation(1, 2, "spouse");

$tree->addMember(3, "Father");
$tree->addRelation(1, 3, "child");
$tree->addRelation(2, 3, "child");

$tree->addMember(4, "Mother");
$tree->addRelation(3, 4, "spouse");

$tree->addMember(6, "Uncle");
$tree->addRelation(4, 6, "sibling");

$tree->addMember(5, "Sir Galahad of the Silvermoon Bastion");
$tree->addRelation(3, 5, "child");
$tree->addRelation(4, 5, "child");

echo $tree->render();

?>
```

The result of the code above will create the an output that looks like this:

![Example](https://raw.githubusercontent.com/darkrunedk/Family-Tree-PHP/refs/heads/main/example.png)
