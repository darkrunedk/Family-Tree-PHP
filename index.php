<?php require_once('classes/FamilyTree.php') ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>Family Tree</title>
    </head>
    <body>

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

    </body>
</html>
