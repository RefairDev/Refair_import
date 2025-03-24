import React, { Component, Fragment } from 'react';

export default class InventoryRow extends Component {

	constructor(props) {
		super(props);
	}

	formatPicture(picObj){
		let picHtml="";
		if (picObj){
			picHtml = picObj.map((elt,idx)=>(<li key={`frag-${elt}-${idx}`} >{elt}</li>));
		}
		return picHtml;
	}


	render() {

        const variationsHtml = this.props.data.variations.map((elt,idx)=>{
			let list_key = elt.ref.replace(".", "_"); 

			let pictureDetails = this.formatPicture(elt.picRefGlob);
			let pictureGlob = this.formatPicture(elt.picRefDetails);

            return (
            <tr key={list_key} className="var-item" >
                <td>{elt.ref}</td>
                <td>{elt.familly}</td>
                <td>{elt.category}</td>
                <td>{elt.designation}</td>
                <td>{elt.type}</td>
                <td>{elt.lng}</td>
                <td>{elt.lrg}</td>
                <td>{elt.htr}</td>
				<td>{elt.qty}</td>
				<td>{elt.unit}</td>
                <td>{elt.surf}</td>
                <td>{elt.condition}</td>
				<td>{elt.description}</td>
                <td>{elt.rqs}</td>
                <td>{elt.placement}</td>
                <td><ul>{pictureGlob}</ul></td>
                <td><ul>{pictureDetails}</ul></td>      
            </tr>)
        });

		let pictureDetails = this.formatPicture(this.props.data.picRefGlob);
		let pictureGlob = this.formatPicture(this.props.data.picRefDetails);

		return (
            <>
			<tr key={this.props.data.ref} className="main-item" >
				<td>{this.props.data.ref}</td>
				<td>{this.props.data.familly}</td>
				<td>{this.props.data.category}</td>
				<td>{this.props.data.designation}</td>
				<td>{this.props.data.type}</td>
				<td>{this.props.data.lng}</td>
				<td>{this.props.data.lrg}</td>
				<td>{this.props.data.htr}</td>
				<td>{this.props.data.qty}</td>				
				<td>{this.props.data.unit}</td>
				<td>{this.props.data.surf}</td>
				<td>{this.props.data.condition}</td>
				<td>{this.props.data.description}</td>
				<td>{this.props.data.rqs}</td>
				<td>{this.props.data.placement}</td>
				<td><ul>{pictureDetails}</ul></td>
				<td><ul>{pictureGlob}</ul></td>      
			</tr>
            {variationsHtml}
            </>
		);
	}
}
