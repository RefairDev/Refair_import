import React, { Component } from 'react';


export default class UploadForm extends Component {

	constructor(props) {
		super(props);
		this.state={
			isSubmitable:false,
			isClearable: false
		}
		this.handleSubmit = this.handleSubmit.bind(this);
		this.handleFileChange = this.handleFileChange.bind(this);
		this.clearFile = this.clearFile.bind(this);
	}

	handleSubmit(e) {
		e.preventDefault(); 

		const files = e.target.inv_file.files;
		this.setState({isSubmitable: false});
		this.props.handleParsing(files);
	}

	handleFileChange(e){
		let clearable =false;
		if (e.target.value != ""){ clearable =true } 
		this.setState({isSubmitable: true, isClearable: clearable, size:e.target.value.length});
		this.props.handleFileChange();
	}

	clearFile(){
		let input_file = document.querySelector("[name='inv_file']");
		if (input_file){input_file.value="";}
		this.setState({isClearable: false});
	}

	render() {

		return (
			<form className="inventoy-form" onSubmit={this.handleSubmit}>
				<input className="inv_file_selector" type="file" name="inv_file" onChange={this.handleFileChange} size={this.state.size}/><button className="inv_file_clear_btn" disabled={!this.state.isClearable} onClick={this.clearFile} >&times;</button>
				<div>
				<input type="submit" value="Analyser" disabled={!this.state.isSubmitable} />
				</div>
			</form>
		);
	}
}