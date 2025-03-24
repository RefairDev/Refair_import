import React, { Component } from 'react';

export default class Row extends Component {

	constructor(props) {
		super(props);
		this.valueToString = this.valueToString.bind(this);
	}

	valueToString(value){
		let returnedStr = "";

		switch (typeof value){

			case "string":
			case "number":
			case "undefined":{
				return value;
			}
			case "object":{
				if(Array.isArray(value)){
					return <ul>{value.map(function(elt,idx){
						let rowValue = this.valueToString(elt);
						return <li key={idx}>{rowValue}</li>;
					},this)}</ul>
				}else{			
					return <ul>{Object.entries(value).map(function([key,value]){
						let rowValue = this.valueToString(value);
						return <li key={key}>{key}: {rowValue}</li>;
					},this)}</ul>
				}
			}
			default:{
				return value;
			}


		}
	}

	render() {
		let rowValue = this.valueToString(this.props.value);
		return (
			<tr>
				<td>{this.props.name}</td>
				<td>{rowValue}</td>
			</tr>
		);
	}
}
