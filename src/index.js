import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import Modal from 'react-modal';
import * as ExcelJS from "exceljs";
import booleanPointInPolygon from "@turf/boolean-point-in-polygon";
import * as turfHelpers from "@turf/helpers";

import UploadForm from './components/UploadForm.js';
import Row from './components/Row.js';
import InventoryRow from './components/InventoryRow.js';
import NotificationRow from './components/NotificationRow.js';

import $ from 'jquery';

const uploadUrl = "/wp-json/xlsinv/v1/upload-deposit";
const setLocUrl = "/wp-json/xlsinv/v1/set-location-file";
const irisUrl =  "/wp-json/xlsinv/v1/get-iris";

function ConvertFormToJSON(form){
    var array = jQuery(form).serializeArray();
    var json = {};
    
    $.each(array, function() {
        json[this.name] = this.value || '';
    });
    
    return json;
}



class App extends Component {

	constructor(props) {
		super(props);

		this.hasFired.bind(this);
		this.canRowFire.bind(this);
		this.handleParsing = this.handleParsing.bind(this);
		this.handleSaving = this.handleSaving.bind(this);
		this.rowIsDone = this.rowIsDone.bind(this);
		this.checkInventory = this.checkInventory.bind(this);
		this.reduceToRef = this.reduceToRef.bind(this);
		this.closeModal = this.closeModal.bind(this);
		this.afterOpenModal = this.afterOpenModal.bind(this);
		this.handleAddressCell = this.handleAddressCell.bind(this);
		this.closeSheetsModal = this.closeSheetsModal.bind(this);
		this.handleSelectOverviewSheetChange = this.handleSelectOverviewSheetChange.bind(this);
		this.handleSelectMaterialsSheetChange = this.handleSelectMaterialsSheetChange.bind(this);
		this.handlePictures = this.handlePictures.bind(this);
		this.handlePicture = this.handlePicture.bind(this);
		this.handleFileChange = this.handleFileChange.bind(this);
		this.checkQty = this.checkQty.bind(this);
		this.checkPrice = this.checkPrice.bind(this);
		this.checkEmpty = this.checkEmpty.bind(this);
		this.checkDate = this.checkDate.bind(this);
		this.checkPicture = this.checkPicture.bind(this);
		this.checkOptPicture = this.checkOptPicture.bind(this);
		this.checkMaterialPicture = this.checkMaterialPicture.bind(this);
		this.processDateCell = this.processDateCell.bind(this);
		this.processUnit = this.processUnit.bind(this);
		this.getCellValueType = this.getCellValueType.bind(this);
		this.getCellValue = this.getCellValue.bind(this);
		this.toggleUpdateQty = this.toggleUpdateQty.bind(this);

		this.apikey = invimport_google_api_key;
		this.chunk  = 50;
		this.firstInventoryRow = 14;
		this.notifSkeleton = JSON.stringify({"level":"","context":"","message":""});

		this.refsCells={
			"radical":0,
			"material":1
		}

		this.cellRefs={
			"overview":{
				"deposit_name": 		{"cell":"D9",						"name":"Nom du gisement",						"options":{}},
				"building_name":  		{"cell":"D10",						"name":"Nom du batiment", 						"options":{}},
				"provider":  			{"cell":"D12",						"name":"Nom du fournisseur", 					"options":{}},
				"address":  			{"cell":"D11",						"name":"Adresse", 								"options":{								"processing": this.handleAddressCell}},
				"city":  				{"cell":"",							"name":"Commune", 								"options":{}},					
				"iris":  				{"cell":"",							"name":"Iris", 									"options":{}},				
				"dismantle_date":		{"cell":"D13",						"name":"Disponibilité prévue", 					"options":{"check":this.checkDate, 		"processing": this.processDateCell}},
				"availability_details":	{"cell":"D14",						"name":"Détails sur disponibilité", 			"options":{}},
				"content":				{"cell":"D15",						"name":"Description",		 					"options":{}},
				"plus_details":			{"cell":"D16",						"name":"Les plus",		 						"options":{}},
				"thumbnail":			{"cell":"D17",						"name":"Photo de mise en avant", 				"options":{"check":this.checkPrice}},
				"photos":				{"cell":["D18","D19","D20","D21"],	"name":"Photos du gisement", 					"options":{"check":this.checkOptPicture}},
				"slug":		            {"cell":"D22",						"name":"Racine d'url",                        	"options":{"check": this.checkEmpty}},
				"template_version":		{"cell":"D24",						"name":"version de la template d'inventaire", 	"options":{}},
			},
			"deposit":{
				"deposit_ref": 	{"cell":"K8",	"name":"Référence du gisement"},
			}

		}		
		this.depositRowRefs = {
			'toExtract':	{cells:['B']},
			'PEMD_code': 	{cells:['C']},
			'PEMD_Macro':	{cells:['D']},
			'PEMD_Cat':		{cells:['E']},
			'PMED_PEM':		{cells:['F']},
			'ref':			{cells:[['H','I']]},
			'familly':		{cells:['J'], 				"check": this.checkEmpty},
			'category':		{cells:['K'], 				"check": this.checkEmpty},
			'designation':	{cells:['L'], 				"check": this.checkEmpty},
			'type':			{cells:['M']},
			'lng':			{cells:['N']},
			'lrg':			{cells:['O']},
			'htr':			{cells:['P']},
			'surf':			{cells:['R']},
			'qty':			{cells:['S'], 				"check": this.checkQty, "default":1},
			'unit':			{cells:['T'], 				"processing": this.processUnit, "default":"u"},
			'condition':	{cells:['Z']},
			'description':	{cells:['AA']},
			'rqs':			{cells:['AB']},
			// 'placement':	{cells:['AB']},
			'picRefGlob':	{cells:['AD'], 				  "check": this.checkMaterialPicture, "processing":  this.handlePicture},
			'picRefDetails':{cells:['AE','AF','AG','AH'], "check": this.checkMaterialPicture, "processing":  this.handlePictures},
			// 'price':		{cells:['BP'], 				  "check": this.checkPrice, "default":1}
		}

		this.globalSaveStatus={
			"none": 	{"message":"","class":""},
			"inprogress":{"message":"En cours","class":""},
			"fail": 	{"message":"L'enregistrement a échoué","class":"fail"},
			"partial": 	{"message":"L'enregistrement a été effectué avec des erreurs","class":"partial"},
			"success": 	{"message":"L'enregistrement est un succès","class":"success"},
			"done": 	{"message":"L'enregistrement n'a pas fait de retour","class":"done"},
		}

		

		this.customStyles = {
			overlay: {
				position: 'absolute',
				top: 0,
				left: 0,
				right: 0,
				bottom: 0,
				backgroundColor: 'rgba(255, 255, 255, 0)'
			  },
			content : {
			top				: '200px',
			left            : '50%',
			right           : 'auto',
			bottom          : 'auto',
			marginRight     : '-50%',
			transform       : 'translate(-50%, -50%)',
			width 			: '300px'
			}
		};
		

		this.invItemDefault = JSON.stringify({"ref":"","familly":"", "category":"", "designation":"", "type":"","variations":[]});

		this.state = {
			search: false,
			done: 0,
			canFire: this.chunk,
			hasFired: 0,
			siteData:{},
			depositData:[],
			updateQty: true,
			notifications:[],
			workbook:[],
			showModal: false,
			showSheetsModal: false,
			addressesProposals:[],
			addressProposalChecked:0,
			status:'none',
			isRecordable: false,
			overviewSheetId:0
		};


	}

	componentDidMount() {
	}

	hasFired () {
		this.setState({
			canFire: this.state.canFire-1,
			hasFired: this.state.hasFired+1
		});
	}

	canRowFire (){
		return this.state.canFire > 0;
	}

	handleParsing (files) {

		const wb = new ExcelJS.Workbook();
		const reader = new FileReader();
		let siteData = {};
		let depositData = [];
		let notifications = [];
		reader.readAsArrayBuffer(files[0]);
		reader.onload = (e) => {
			const buffer = reader.result;
			wb.xlsx.load(buffer).then(workbook => {
				console.log(workbook, 'workbook loaded');
				let selectedImportWS = workbook.worksheets.reduce((acc, elt)=> elt.name.includes("IMPORT")? elt.id: acc,0);	
				this.setState({"workbook":workbook, "showSheetsModal":true, "isRecordable":false,"overviewSheetId":Number.parseInt(selectedImportWS)});
			});
		}

	}

	handleFileChange(){
		let newState = this.state;
		newState.notifications=[];
		newState.siteData={};
		newState.depositData=[];
		this.setState(newState);
	}

	extractAllData(){

		let {materialSheetId} = this.state;
		let {overviewSheetId} = this.state;
		let {notifications} = this.state;

		if (this.checkInventoryReferences(overviewSheetId,materialSheetId)){
			//Overview Worksheet Handling
			let siteData = this.extractOverview(overviewSheetId);

			//Inventory Worksheet Handling
			let depositData = this.extractRows(materialSheetId);

			let invNotif = this.checkInventory(depositData);
			notifications = notifications.concat(invNotif);

			this.setState({"siteData":siteData, "depositData":depositData});
		}
		


		this.setState({"notifications":notifications});

	}

	extractOverview(overviewSheetId){

		let {workbook} = this.state;
		let overviewWS= workbook.getWorksheet(overviewSheetId);
		let overviewCellRefs = this.cellRefs["overview"];
		let siteData={};
		let mgCell = this.manageCellRef;
		for (let cellRef in overviewCellRefs){
			if (overviewCellRefs[cellRef]['cell'] !=""){
				if (Array.isArray(overviewCellRefs[cellRef]['cell'])){					
					siteData[cellRef]= overviewCellRefs[cellRef]['cell'].map((element )=> {
						return mgCell(overviewWS.getCell(element),overviewCellRefs[cellRef]['options']);
					})					

				}else{
					siteData[cellRef]= mgCell(overviewWS.getCell(overviewCellRefs[cellRef]['cell']),overviewCellRefs[cellRef]['options']);
				}
			}
		}

		return siteData;
	}

	manageCellRef(cell,options){

		let value=options.hasOwnProperty("default")?options["default"]:"";
		let isValid = true;

		if (options.hasOwnProperty('check') && !options["check"](cell)){
			isValid = false;
		}
			
		if (isValid){
			if (options.hasOwnProperty('processing')){
				value = options['processing'](cell);
			}else{
				value = App.prototype.getCellValue(cell,App.prototype.getCellValueType(cell.value));
			}
		}

		return value;
	}

	extractRows (materialSheetId){

		let {workbook} = this.state;
		let depositData=[];
		let depositWS=workbook.getWorksheet(materialSheetId);

		let invRef = depositWS.getCell(this.cellRefs["deposit"]['deposit_ref']['cell']).text;
		let invDismantleDate = depositWS.getCell(this.cellRefs["deposit"]['deposit_ref']['cell']).text;
		let invItem = JSON.parse(this.invItemDefault);

		depositWS.eachRow((row,rwnb)=>{
			if(rwnb > this.firstInventoryRow) {
				let rowRefRadCell = row.getCell(this.depositRowRefs["ref"]['cells'][0][this.refsCells.radical]);
				let rowRadRef = this.getCellValue(rowRefRadCell,this.getCellValueType(rowRefRadCell.value));
				if (invRef == rowRadRef && this.isToExtract(row)){

					if ( this.isVariation(row)){
						let variation = this.extractRow(row);
						invItem['variations'].push(variation);
					}else{
						if (invItem.ref!=""){
							depositData.push(invItem);
							invItem = JSON.parse(this.invItemDefault);
						}
						invItem = this.extractRow(row);
						invItem['deposit'] = invRef;
					}
					
				} 
			}
		});
		depositData.push(invItem);

		return depositData;
	}

	extractRow (row){
		var extracted_data = JSON.parse(this.invItemDefault);
		for (const [name, props] of Object.entries(this.depositRowRefs)) {
			let data="";
			let isValid = true;
			if (props.hasOwnProperty('check')){
				isValid = props['cells'].map((element )=> {
					return props["check"](row.getCell(element));
				}).reduce((acc,elt)=>!elt ? acc=elt:acc ,true);
			}
			if (isValid){
				if (props.hasOwnProperty('processing')){
					let cellsValues = "";
					if ( 1 == props['cells'].length ) {
						cellsValues = props["processing"]( row.getCell(props['cells'][0]), props );
					} else {
						cellsValues = props['cells'].map((element )=> {
							return props["processing"]( row.getCell(element), props );
						});
					}
					extracted_data[name] = cellsValues;
					continue;
				}
				props['cells'].forEach((element,idx,colsArray )=> {
					if (Array.isArray(element)){					
						data = element.reduce((pv,cv,ci,arr)=>{return pv+=row.getCell(cv).text},"");
					}else{
						if (colsArray.length<2){
							data = this.getCellValue(row.getCell(element),this.getCellValueType(row.getCell(element).value));
						}else{
							if(idx==0){data=[];}
							let cellValue = this.getCellValue(row.getCell(element),this.getCellValueType(row.getCell(element).value));
							if (cellValue && cellValue != ""){
								data.push(cellValue);
							}
						}					
					}
				});
			}
			extracted_data[name] = data;
			
		}	
		return extracted_data;
	}

	isVariation(rowTest){

		let rowRefCell= rowTest.getCell(this.depositRowRefs["ref"]['cells'][0][this.refsCells.material]);
		let rowRef= this.getCellValue(rowRefCell,this.getCellValueType(rowRefCell.value));

		const regex = /\.[0-9]{3}$/;
		return regex.test(rowRef);
	}

	isToExtract(row){

		let isToExtract=false;
		let cellValue = row.getCell(this.depositRowRefs["toExtract"]['cells'][0]).text;
		if (cellValue == "true" || cellValue == true || cellValue == "1"|| cellValue == 1){
			isToExtract = true;
		}

		return isToExtract;
	}

	isOptionalPicture(cell){

		let isOptional=true;
		let celCol = this.getColumnLetter(cell);
		if (( celCol === this.depositRowRefs["picRefGlob"]['cells'][0]) && !this.isVariation(cell.worksheet.getRow(cell.row))){
			isOptional =false;
		}

		return isOptional;
	}

	getColumnLetter(cell){

		return cell.address.replace(cell.row,"");
	}

	checkInventory(deposit){
		let notifications = [];
		
		let deposit_ref = deposit.reduce(this.reduceToRef,[]);
		let doubleRefs = deposit_ref.filter((element, index, array) => array.indexOf(element) !== index)
		if (doubleRefs.length >0){
			doubleRefs.forEach(element => {
				let notification = JSON.parse(this.notifSkeleton);
				notification.level = "Erreur";
				notification.context = "Vérifications Réferences";
				notification.message = "Reference en double: "+ element;
				notifications.push(notification);
			});
		}
		return notifications;
	}

	checkInventoryReferences(overviewSheetID,materialsSheetID){
		let overviewWS = this.state.workbook.getWorksheet(overviewSheetID);
		let materialsWS = this.state.workbook.getWorksheet(materialsSheetID);
		let overview_deposit_ref = this.getCellValue(overviewWS.getCell(this.cellRefs['overview']['deposit_name']['cell']),this.getCellValueType(overviewWS.getCell(this.cellRefs['overview']['deposit_name']['cell']).value));
		let materials_deposit_ref = this.getCellValue(materialsWS.getCell(this.cellRefs['deposit']['deposit_ref']['cell']),this.getCellValueType(materialsWS.getCell(this.cellRefs['deposit']['deposit_ref']['cell']).value));

		if (overview_deposit_ref !== materials_deposit_ref){
			this.addNotification("ERREUR","Vérification Référence gisement",`Les références de gisement diffèrent entre la feuille d'import (${overview_deposit_ref}) et la feuille de matéraiux (${materials_deposit_ref})`);
			return false;
		}
		return true;
	}

	checkQty(cell){
		let isValid = true;
		let value = this.getCellValue(cell,this.getCellValueType(cell.value));
		if (Number.isNaN(parseInt(value)) || value == 0 ){
			isValid =false;
			let refRadName =cell.worksheet.getRow(cell.row).getCell(this.depositRowRefs["ref"]['cells'][0][this.refsCells.radical]).text;
			let refMatName =cell.worksheet.getRow(cell.row).getCell(this.depositRowRefs["ref"]['cells'][0][this.refsCells.material]).text;
			let refName = refRadName+refMatName;
			this.addNotification("Attention","Vérification Quantité",`Quantité non valide pour  de ${refName} (${cell.address}): ${value}`);
		}
		return isValid;
	}

	checkEmpty(cell){
		let isValid = true;
		let value = this.getCellValue(cell,this.getCellValueType(cell.value));
		if ( ( ! value ) || value === "" || value === "-"){
			isValid =false;
			let colName = cell.worksheet.getRow(9).getCell(cell.col).value;
			let refRadName =cell.worksheet.getRow(cell.row).getCell(this.depositRowRefs["ref"]['cells'][0][this.refsCells.radical]).text;
			let refMatName =cell.worksheet.getRow(cell.row).getCell(this.depositRowRefs["ref"]['cells'][0][this.refsCells.material]).text;
			let refName = refRadName+refMatName;
			this.addNotification("Attention","Vérification cellule",`l'info ${colName} de ${refName} est vide ${cell.address}`);
		}
		return isValid;
	}

	checkDate(cell){
		let isValid = true;
		let value = this.getCellValue(cell,this.getCellValueType(cell.value));
		if (ExcelJS.ValueType.Date == cell.type){
			return isValid;
		};

		var date_regex = /^(0[1-9]|1\d|2\d|3[01])\/(0[1-9]|1[0-2])\/(19|20)\d{2}$/;	
		if (!cell.value || !(date_regex.test(cell.value))){
			isValid = false;
			this.addNotification("Attention","Vérification Date",`Date non valide pour ${cell.address}: ${cell.value}`);
		}
		return isValid;
	}

	checkPrice(cell){
		let isValid = true;
		if (Number.isNaN(cell.value)){
			isValid = false;
			this.addNotification("Attention","Vérification prix",`Prix non valide pour ${cell.address}: ${cell.value}`);
		}
		return isValid;
	}

	checkPicture(cell){
		let isValid = true;
		let cellValue = this.getCellValue(cell,this.getCellValueType(cell.value));
		// let {overviewSheetId} = this.state;
		// let {workbook} = this.state;
		//let ws = workbook.getWorksheet(overviewSheetId);

		if (!cellValue || cellValue === "" || cellValue === "-" || (cellValue.length < 6)){
			isValid=false;
			this.addNotification("Attention","Vérification Image",`Nom de référence d'image invalide pour ${cell.address}:${cellValue}`);
		}
		return isValid;
	}

	checkOptPicture(cell){
		let isValid = true;
		let cellValue = this.getCellValue(cell,this.getCellValueType(cell.value));
		if (cellValue && ((cellValue === "")  || (cellValue === "-"))){
			isValid=false;
		}
		if (isValid && cellValue  && (cellValue.length < 6)){
			isValid=false;
			this.addNotification("Attention","Vérification Image",`Nom de référence d'image invalide pour ${cell.address}:${cellValue}`);
		}
		return isValid;
	}	

	checkMaterialPicture(cell)
	{
		if (this.isOptionalPicture(cell)){
			return this.checkOptPicture(cell);
		}
		// if not stay in function		
		return this.checkPicture(cell);
	}

	getCellValueType(cellValue){

		let type= ExcelJS.ValueType.Null;
		let types=[
			{prop:"formula",type:ExcelJS.ValueType.Formula},
			{prop:"sharedFormula",type:ExcelJS.ValueType.Formula},
			{prop:"hyperlink",type:ExcelJS.ValueType.Hyperlink},
			{prop:"richText",type:ExcelJS.ValueType.RichText},
			{prop:"error",type:ExcelJS.ValueType.Error}
					]

		if (cellValue != null){
			switch (typeof cellValue){
				case "number":
					{
						type = ExcelJS.ValueType.Number;
						break;
					}
				case "boolean":
					{
						type = ExcelJS.ValueType.Boolean;
						break;
					}
				case "string": 
					{
						type = ExcelJS.ValueType.String;
						break;
					}
				case "object":
					{
						let filtered = types.filter((elt)=>cellValue.hasOwnProperty(elt.prop));
						if(filtered[0]){type = filtered[0].type;}
						break;
					}
			}
		}
		return type;

	}

	getCellValue(cell, type){

		let returned;
		switch (type){
			case ExcelJS.ValueType.Number:
			case ExcelJS.ValueType.Boolean:
			case ExcelJS.ValueType.String: 
				{
					returned = cell.value;
					break;
				}
			case ExcelJS.ValueType.Formula:
				{
					let regex = /^([A-Za-z0-9_]+\!)?[A-Z]+[1-9]\d*$/; /* cell coordinate pattern AA..00..*/
					
					if ( regex.test(cell.value.sharedFormula) === true ) {
						let linkedCell = cell.worksheet.getCell(cell.value.sharedFormula);
						returned = this.getCellValue(linkedCell,this.getCellValueType(linkedCell.value));
					} else{
						returned = (cell.result)?cell.result:0;	
					}

					break;
				}
			case ExcelJS.ValueType.Hyperlink:
				{
					returned = cell.text;
					if (cell.text.hasOwnProperty('richText')){
						returned = cell.text.richText.reduce((acc,elt)=>acc+elt.text,'');
					}
					break;
				}						
			case ExcelJS.ValueType.Error:
				{
					returned = cell.error;
					break;
				}	

			case ExcelJS.ValueType.RichText:
				{					
					returned = cell.value.richText.reduce((acc,elt)=>acc+elt.text,'');
					break;
				}
		}
		return returned;
	}

	reduceToRef(acc,v){
		acc.push(v.ref);
		if (v.variations.length >0){
			v.variations.reduce(this.reduceToRef,acc);
		}
		return acc;
	}

	handleSaving(e){
		e.preventDefault();

		let body = JSON.stringify({"siteData":this.state.siteData, "depositData":this.state.depositData, "update_qty": this.state.updateQty});

		this.setState({"status":"inprogress"});

		fetch(uploadUrl, {
			method: 'POST', // or 'PUT'
			headers: {
				'Content-Type': 'application/json',
			},
			body: body,
		}).then(function(response) {

			let saveStatus="";
			switch(response.status){
				case 500:
				{					
					alert("une erreur est survenue lors de l'enregistrement des données");
					saveStatus= 'fail';
					break;
				}
				case 404:
				{
					alert("Le serveur n'a pas été trouvé pour l'enregistrement des données");
					saveStatus= 'fail';
					break;
				}
				case 206:
				{
					saveStatus= 'partial';
					break;
				}
				case 200:
				{
					saveStatus= 'success';
					break;
				}
				default:
				{
					saveStatus= 'done';
				}
			}

			this.setState({status:saveStatus});
			return response.json();
			
		}.bind(this)).then(respBody =>{
			if (respBody.hasOwnProperty('data') && respBody.data.hasOwnProperty('status') && respBody.data.status ==500){
				return;
			}
			if (Array.isArray(respBody)){
				let newNotifications = this.state.notifications.concat(respBody);
				this.setState({notifications:newNotifications});
			}
		});

	}

	rowIsDone() {
		this.setState({
			done: this.state.done + 1
		});
	}

	handleAddressCell(cell){

		let cellValue = App.prototype.getCellValue(cell,App.prototype.getCellValueType(cell.value));

		fetch(`https://maps.googleapis.com/maps/api/geocode/json?address=${cellValue}&language=fr&region=fr&key=${this.apikey}`)
		.then(function(response) {
			return response.json();
		})
		.then(function(resp){
				if(! this.state.showModal){
					let addressesProposals=[];
					switch(resp.status)
					{
						case "OK":
						{
							resp.results.forEach(function(valeurCourante,index ,resultArray){
								addressesProposals.push({"location":valeurCourante.formatted_address,"lat": valeurCourante.geometry.location.lat,"lng":valeurCourante.geometry.location.lng }) ;
							});
							break;
						}
						case"ZERO_RESULTS":
						{
							alert("No result returned, correct and renew your request"); 
							break;
						}
						default:
						{
							alert('Error: Google geocode returned "'+ resp.status +'"' );
							
						}
					}
					return addressesProposals;
				}
			}.bind(this)
		).then(function(addressesProposals){
			if (addressesProposals){
				this.setState({"addressesProposals":addressesProposals, "showModal":true});
			}
		}.bind(this));

		return "en cours";
	}

	processDateCell(cell){

		if (cell.type == ExcelJS.ValueType.Date){
			let date = new Date(cell.value);
			return `${date.getFullYear()}-${("0" + (date.getMonth()+1)).slice(-2)}-${("0" + date.getDate()).slice(-2)}`;
		}else{
			var date_regex = /^(0[1-9]|1\d|2\d|3[01])\/(0[1-9]|1[0-2])\/(19|20\d{2})$/;	
			if (cell.value && (date_regex.test(cell.value))){
				let digits = date_regex.exec(cell.value);
				return `${digits[2]}-${digits[1]}-${digits[0]}`;
			}
			return cell.value;
		}
	}

	processUnit(cell, props){
		let cellValue = this.getCellValue(cell,this.getCellValueType(cell.value));

		if ( ! cellValue || cellValue === "" ) {
			cellValue = props.default;
		}

		return cellValue;
	}

	handleLocationProposalChange(event){
		this.setState({addressProposalChecked:event.target.value});
	}

  
	afterOpenModal() {
	  // references are now sync'd and can be accessed.
	}
  
	closeModal(){
	  	let {siteData} = this.state;
	  	let exactAddress = this.state.addressesProposals[this.state.addressProposalChecked];

		/* TODO get public location */
		  
		let city= this.extractCity(exactAddress.location);
		this.getIris(city,[exactAddress.lng,exactAddress.lat]);
		siteData.address = exactAddress;
		siteData.city = city;
	  	this.setState({"siteData" : siteData,"showModal":false});
	}

	extractCity(fullAddress){
		let city= "";
		const regex = /, [0-9]{5} (.*), /;
		let m;
		if ((m = regex.exec(fullAddress)) !== null) {
			// The result can be accessed through the `m`-variable.
			city = m[1];
		}
		/* TODO get city form extact adress*/
		return city;
	}

	getIris(city,coords){
		let iris={};

		fetch(irisUrl+`/?city=${city}&coords=${coords}`, {
			method: 'GET', // or 'PUT'
			headers: {
				'Content-Type': 'application/json',
			},
		}).then(function(response) {

			return response.json();
			
		}.bind(this)).then(respBody =>{

			let {siteData}= this.state;
			siteData.iris = respBody;
	  		this.setState({"siteData":siteData,"showModal":false, "isRecordable":true});			
		});
		
		/* TODO get iris from iris list with city name*/
	}


	handleSelectOverviewSheetChange(event){
		this.setState({"overviewSheetId":Number.parseInt(event.target.value)});
	}

	handleSelectMaterialsSheetChange(event){
		this.setState({"materialSheetId":Number.parseInt(event.target.value)});
	}


	closeSheetsModal(){
		this.extractAllData();
		this.setState({"showSheetsModal":false});
	}

	handlePicture(cell,props){

		let returned = this.handlePictures(cell,props)
		return [returned];
	}

	handlePictures(cell,props){

		let cellValue = this.getCellValue(cell,this.getCellValueType(cell.value))
		return cellValue;
	}

	toggleUpdateQty(){
		this.setState({
			updateQty: !this.state.updateQty,
		  });
	}

	addNotification(level,ctx, msg){

		let {notifications} = this.state;
		let notification = JSON.parse(this.notifSkeleton);
		notification.level = level;
		notification.context = ctx;
		notification.message = msg;
		notifications.push(notification);
		this.setState({"notifications":notifications});

	}

	render() {

		const rows = Object.entries(this.state.siteData).map(
			([key, value],idx)=>(<Row key={key} name={this.cellRefs["overview"][key]['name']} value={value} />)
		);

		const depositRows = this.state.depositData.map(
			(elt,idx)=>(<InventoryRow  key={elt.ref} data={elt} />)
		);
		let notificationRows = "";

		if (Array.isArray(this.state.notifications)){
			 notificationRows = this.state.notifications.map(
				(elt,idx)=>(<NotificationRow key={idx} data={elt} />)
			);
		}



		return (
			<>
			<Modal
			isOpen={this.state.showModal}
			onAfterOpen={this.afterOpenModal}
			onRequestClose={this.closeModal}
			style={this.customStyles}
			contentLabel="Sélection d'adresse"
			parentSelector={() => document.querySelector('#wpcontent')}
			portalClassName="adresses-portal-modal" 
			shouldCloseOnOverlayClick={false}
			shouldCloseOnEsc={false}
			>
				<h2>Sélectionner l'adresse correspondante à celle extraite</h2>
				<div>
				{this.state.addressesProposals.map((elt,idx)=> <div key={idx}><input id={`selectedAddress-${idx}`}  type="radio" name="selectedAddress" value={idx} onChange={this.handleLocationProposalChange} checked={idx==this.state.addressProposalChecked} /><label htmlFor={`selectedAddress-${idx}`}>{elt.location}</label></div>)}
				</div>
				<button onClick={this.closeModal}>Valider</button>
			</Modal>
			<Modal
			isOpen={this.state.showSheetsModal}
			onAfterOpen={this.afterOpenSheetsModal}
			onRequestClose={this.closeSheetsModal}
			style={this.customStyles}
			contentLabel="Sélection des feuilles de classeur"
			parentSelector={() => document.querySelector('#wpcontent')}
			portalClassName="sheets-portal-modal"
			>
				<h2>Sélectionner Les feuilles du classeur de gisement</h2>
				<div>
					<label forhtml="select-sheet-overview">Feuille d'import</label>
					<select className="modal-label" id="select-sheet-overview" value={this.state.overviewSheetId} onChange={this.handleSelectOverviewSheetChange}>
						{this.state.workbook.worksheets && this.state.workbook.worksheets.map((elt,idx)=> <option key={idx} value={elt.id}>{elt.name}</option>)}
					</select>
					<label className="modal-label" forhtml="select-sheet-materials">Feuille de page d'inventaire</label>
					<select id="select-sheet-materials" onChange={this.handleSelectMaterialsSheetChange}>
						{this.state.workbook.worksheets && this.state.workbook.worksheets.map((elt,idx)=> <option key={idx} value={elt.id}>{elt.name}</option>)}
					</select>				
				</div>
				<button onClick={this.closeSheetsModal}>Valider</button>
			</Modal>

			<UploadForm handleParsing={this.handleParsing} handleFileChange={this.handleFileChange} />
			{(Object.keys(this.state.siteData).length >0) && (<>
			<button onClick={this.handleSaving}  disabled={!this.state.isRecordable} >Enregistrer</button>
			<div className="inline-input" ><input id='update-qty-input' type="checkbox" defaultChecked={this.state.updateQty} onChange={this.toggleUpdateQty} disabled={!this.state.isRecordable}/><label htmlFor='update-qty-input'>Mettre à jour les quantités</label></div>
			</>)}
			{this.state.status =="inprogress" && <div className="recording"><div className="lds-ellipsis"><div></div><div></div><div></div><div></div></div></div>}<span className={"save-status "+ this.globalSaveStatus[this.state.status]['class']}>{(Object.keys(this.state.siteData).length >0) && this.globalSaveStatus[this.state.status]['message']}</span>

			<div className="container">
				{(Object.keys(this.state.siteData).length >0) && (
				<div className="col-6">
					<h2>Gisement</h2>
					<table className="deposit-table">
						<tbody>
						{rows}
						</tbody>
					</table>
				</div>
				)}
				{(this.state.notifications.length >0 &&
				<div className="col-6">
					<h2>Notifications</h2>
					<table className="notif-table">
						<thead><tr><th>Niveau</th><th>Contexte</th><th>Message</th></tr></thead>
						<tbody>
						{notificationRows}
						</tbody>
					</table>	
				</div>
				)}
			</div>				
			{(this.state.depositData.length >0 &&
				<div>
					<h2>Matériaux</h2>
					<table className="materials-table">
						<thead>
							<tr>
								<th>Référence</th>
								<th>Famille</th>
								<th>Catégorie</th>
								<th>Désignation</th>
								<th>Type/Matériau</th>
								<th>Longueur</th>
								<th>Largeur</th>
								<th>Hauteur</th>
								<th>Quantité</th>
								<th>Unité</th>
								<th>surface</th>
								<th>Status</th>
								<th>Description</th>
								<th>Remarque</th>
								<th>Emplacement</th>
								<th>Photo pricipale</th>
								<th>Autres photos</th>
							</tr>
						</thead>
						<tbody>
						{depositRows}
						</tbody>
					</table>
				</div>
			)}

			</>
		)	
	}

}

window.addEventListener('load', () => {
	Modal.setAppElement('#app');
	ReactDOM.render(<App />, document.getElementById('app'));
});




