<?php

/* script to show subnet details, addresses etc */

# verify that user is logged in
$User->check_user_session();

# subnet id must be numeric
if(!is_numeric($_GET['subnetId'])) 	{ $Result->show("danger", _('Invalid ID'), true); }

# fetch subnet related stuff
$custom_fields = $Tools->fetch_custom_fields ('subnets');											//custom fields
$subnet  = $Subnets->fetch_subnet(null, $_GET['subnetId']);									//subnet details
if($subnet===false) 				{ header("Location: ".create_link("subnets", $_GET['section'])); die(); }	//redirect if false
else { $subnet = (array) $subnet; }
$subnet_detailed = $Subnets->get_network_boundaries ($subnet['subnet'], $subnet['mask']);			//set network boundaries
$slaves = $Subnets->has_slaves ($subnet['id']) ? true : false;										//check if subnet has slaves and set slaves flag true/false

# if subnet is requested but is folder redirect
if ($subnet['isFolder']==1) { header("Location: ".create_link("folder", $_GET['section'], $_GET['subnetId'])); }

# permissions
$subnet_permission  = $Subnets->check_permission($User->user, $subnet['id']);						//subnet permission
$section_permission = $Sections->check_permission($User->user, $subnet['sectionId']);				//section permission
if($subnet_permission == 0)			{ $Result->show("danger", _('You do not have permission to access this network'), true); }

# fetch VLAN details
$vlan = (array) $Tools->fetch_object("vlans", "vlanId", $subnet['vlanId']);

# fetch recursive nameserver details
$nameservers = (array) $Tools->fetch_object("nameservers", "id", $subnet['nameserverId']);

# fetch all addresses and calculate usage
if($slaves) {
	$addresses = $Addresses->fetch_subnet_addresses_recursive ($subnet['id'], false);
	$slave_subnets = (array) $Subnets->fetch_subnet_slaves ($subnet['id']);
	// save count
	$addresses_cnt = gmp_strval(sizeof($addresses));

	# full ?
	if (sizeof($slave_subnets)>0) {
    	foreach ($slave_subnets as $ss) {
        	if ($ss->isFull==1) {
            	# calculate max
            	$max_hosts = $Subnets->get_max_hosts ($ss->mask, $Subnets->identify_address($ss->subnet), true);
            	# count
            	$count_hosts = $Addresses->count_subnet_addresses ($ss->id);
            	# add
            	$addresses_cnt = gmp_strval(gmp_add($addresses_cnt, gmp_sub($max_hosts, $count_hosts)));
        	}
    	}
	}

	$subnet_usage  = $Subnets->calculate_subnet_usage ($addresses_cnt, $subnet['mask'], $subnet['subnet'], $subnet['isFull'] );		//Calculate free/used etc
} else {
	$addresses = $Addresses->fetch_subnet_addresses ($subnet['id']);
	// save count
	$addresses_cnt = gmp_strval(sizeof($addresses));
	$subnet_usage  = $Subnets->calculate_subnet_usage ($addresses_cnt, $subnet['mask'], $subnet['subnet'], $subnet['isFull'] );		//Calculate free/used etc
}

# verify that is it displayed in proper section, otherwise warn!
if($subnet['sectionId']!=$_GET['section'])	{
	$sd = (array) $Sections->fetch_section(null,$subnet['sectionId']);
	$Result->show("warning", _("Subnet is in section")." <a href='".create_link("subnets",$sd['id'],$subnet['id'])."'>$sd[name]</a>!", false);
}

# set title
$location = "subnets";

# NAT search
$all_nats = array();
$all_nats_per_object = array();

if ($User->settings->enableNAT==1) {
    # fetch all object
    $all_nats = $Tools->fetch_all_objects ("nat");

    if ($all_nats!==false) {
        foreach ($all_nats as $n) {
            $out[$n->id] = $n;
        }
        $all_nats = $out;

        # reindex
        $all_nats_per_object = $Tools->reindex_nat_objects ($all_nats);
    }
}
?>

<!-- content print! -->
<div class="row" style="margin-bottom: 40px;">

	<!-- subnet details -->
	<div class="col-sm-12 col-xs-12 <?php if(@$_GET['sPage']=="changelog") { print "col-lg-12 col-md-12";} else { print "col-lg-8 col-md-8"; } ?>">
		<!-- for adding IP address! -->
		<div id="subnetId" style="display:none;"><?php print $subnet['id']; ?></div>

        <!-- subnet details upper table -->
        <h4><?php print _('Subnet details'); ?></h4>
        <hr>

        <!-- tabs -->
        <ul class='nav nav-tabs ip-det-switcher' style='margin-bottom:20px;'>
            <li role='presentation' <?php if(!isset($_GET['sPage'])) print " class='active'"; ?>> <a href='<?php print create_link("subnets", $subnet['sectionId'], $subnet['id']); ?>'><?php print _("Subnet details"); ?></a></li>
            <?php if($User->is_admin(false)) { ?>
            <li role='presentation' <?php if(@$_GET['sPage']=="permissions") print "class='active'"; ?>><a href='<?php print create_link("subnets", $subnet['sectionId'], $subnet['id'], "permissions"); ?>'><?php print _("Permissions"); ?></a></li>
            <?php } ?>
            <?php if($User->settings->enableNAT==1) { ?>
            <li role='presentation' <?php if(@$_GET['sPage']=="nat") print "class='active'"; ?>> <a href='<?php print create_link("subnets", $subnet['sectionId'], $subnet['id'], "nat"); ?>'><?php print _("NAT"); ?></a></li>
            <?php } ?>
            <li role='presentation' <?php if(@$_GET['sPage']=="changelog") print " class='active'"; ?>> <a href='<?php print create_link("subnets", $subnet['sectionId'], $subnet['id'], "changelog"); ?>'><?php print _("Changelog"); ?></a></li>
        </ul>

        <!-- details -->
        <?php
        if(!isset($_GET['sPage'])) {
        	include("subnet-details/subnet-details.php");
        }
        if(@$_GET['sPage']=="permissions") {
            include("subnet-details/subnet-permissions.php");
        }
        if($User->settings->enableNAT==1 && @$_GET['sPage']=="nat") {
            include("subnet-details/subnet-nat.php");
        }
        if(@$_GET['sPage']=="changelog") {
            include("subnet-details/subnet-changelog.php");
        }
    	?>

	</div>

    <?php if(@$_GET['sPage']!="changelog") { ?>

	<!-- subnet graph -->
	<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
		<?php include('subnet-details/subnet-graph.php'); ?>
	</div>

	<!-- subnet slaves list -->
	<div class="col-xs-12 subnetSlaves">
		<?php if($slaves) include('subnet-slaves.php'); ?>
	</div>

	<!-- addresses -->
	<div class="col-xs-12 ipaddresses_overlay">
		<?php include('addresses/print-address-table.php'); ?>
	</div>

	<!-- visual subnet display -->
	<div class="col-xs-12">
	<?php
	if($Subnets->identify_address($subnet['subnet']) == "IPv4") {
		if($User->settings->visualLimit > 0) {
			if($User->settings->visualLimit <= $subnet['mask'] && !$slaves) {
				include('subnet-visual.php');
			}
		}
	}
	?>
	</div>

	<!-- orphaned addresses -->
	<div class="col-xs-12">
		<?php
		# if subnet has slaves check if also has addresses - if so they are orphaed, print them!
		if($slaves) {
			$addresses = $Addresses->fetch_subnet_addresses ($subnet['id']);
			if(sizeof($addresses)>0) {
				# set flag
				$orphaned = true;
				include('addresses/print-address-table.php');
			}
		}
		?>
	</div>
	<?php } ?>

</div>


