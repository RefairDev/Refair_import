import React, { Component } from 'react';

export default class NotificationRow extends Component {

	constructor(props) {
		super(props);
	}
	render() {

		return (
            <>
			<tr className="notification-item" >
				<td>{this.props.data.level}</td>
				<td>{this.props.data.context}</td>
				<td>{this.props.data.message}</td>   
			</tr>
            </>
		);
	}
}
